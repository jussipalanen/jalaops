"""Tests for jalaops_module. Run in backend/modules/sdk: python3 -m unittest"""

import io
import json
import os
import tempfile
import typing
import unittest
from pathlib import Path
from unittest import mock

from jalaops_module import InputError, Module, action, feed_item


class NewsModule(Module):
    @action("list", default=True)
    def list_news(self, tag: str | None = None, limit: int = 20):
        return {"tag": tag, "limit": limit}

    @action("get")
    def get_news(self, id: int):
        if id != 1:
            raise InputError("Uutista ei löytynyt.")
        return {"id": id}

    @action("types", labels={"amount": "Veroton hinta"})
    def types(
        self,
        amount: float = 0.0,
        rate: typing.Literal["25.5", "14"] = "25.5",
        numbers: list[float] | None = None,
        tags: list[str] | None = None,
        done: bool = False,
        options: dict | None = None,
        anything=None,
    ):
        return {"amount": amount, "rate": rate, "numbers": numbers, "tags": tags, "done": done, "options": options, "anything": anything}

    @action("rest")
    def rest(self, name: str, **rest):
        return {"name": name, "rest": rest}

    @action("broken")
    def broken(self):
        return 1 / 0

    @action("not-a-dict")
    def not_a_dict(self):
        return ["a list"]

    def helper(self):
        """Not an action: the input can't call it."""
        return {"called": True}


def run(module, text):
    stdout, stderr = io.StringIO(), io.StringIO()
    code = module.run(io.StringIO(text), stdout, stderr)
    return code, stdout.getvalue(), stderr.getvalue()


class ActionsTest(unittest.TestCase):
    def setUp(self):
        self.module = NewsModule()

    def test_runs_the_default_action_without_an_action(self):
        self.assertEqual(self.module.handle({}), {"tag": None, "limit": 20})

    def test_picks_the_action_and_passes_the_values(self):
        self.assertEqual(self.module.handle({"action": "list", "tag": "huolto", "limit": 5}), {"tag": "huolto", "limit": 5})
        self.assertEqual(self.module.handle({"action": "get", "id": 1}), {"id": 1})

    def test_ignores_values_the_action_does_not_take(self):
        self.assertEqual(self.module.handle({"action": "get", "id": 1, "extra": "x"}), {"id": 1})

    def test_passes_the_other_values_to_a_method_with_keyword_arguments(self):
        self.assertEqual(self.module.handle({"action": "rest", "name": "a", "b": 2}), {"name": "a", "rest": {"b": 2}})

    def test_refuses_an_unknown_action_and_methods_that_are_not_actions(self):
        for name in ("delete", "helper", 3):
            with self.subTest(name=name), self.assertRaisesRegex(InputError, "^Tuntematon toiminto"):
                self.module.handle({"action": name})

    def test_needs_an_action_without_a_default(self):
        class NoDefault(Module):
            @action("get")
            def get(self):
                return {}

        with self.assertRaisesRegex(InputError, r"^Anna toiminto \(action\)\.$"):
            NoDefault().handle({})

    def test_the_input_must_be_an_object(self):
        with self.assertRaisesRegex(InputError, "^Syötteen pitää olla JSON-objekti.$"):
            self.module.handle([1, 2])

    def test_only_one_action_can_be_the_default(self):
        class TwoDefaults(Module):
            @action("a", default=True)
            def a(self):
                return {}

            @action("b", default=True)
            def b(self):
                return {}

        with self.assertRaises(TypeError):
            TwoDefaults().handle({})

    def test_an_action_method_cant_replace_the_base_class_methods(self):
        with self.assertRaisesRegex(TypeError, r"run\(\) would replace Module.run\(\)"):

            class Clash(Module):
                @action("run", default=True)
                def run(self):
                    return {}


class ChecksTest(unittest.TestCase):
    def setUp(self):
        self.module = NewsModule()

    def check(self, values):
        return self.module.handle({"action": "types", **values})

    def test_accepts_good_values(self):
        output = self.check({"amount": 10, "rate": "14", "numbers": [1, 2.5], "tags": ["a"], "done": True, "options": {"a": 1}, "anything": [None]})

        self.assertEqual(output, {"amount": 10, "rate": "14", "numbers": [1, 2.5], "tags": ["a"], "done": True, "options": {"a": 1}, "anything": [None]})
        self.assertIsInstance(output["amount"], int)

    def test_optional_values_can_be_null(self):
        self.assertIsNone(self.check({"numbers": None})["numbers"])
        self.assertIsNone(self.module.handle({"action": "list", "tag": None})["tag"])

    def test_refuses_wrong_types_in_finnish(self):
        for values, message in [
            ({"amount": "sata"}, "Veroton hinta: anna numero."),
            ({"amount": True}, "Veroton hinta: anna numero."),
            ({"rate": "24"}, "rate: valitse jokin näistä: 25.5, 14."),
            ({"rate": 14}, "rate: valitse jokin näistä: 25.5, 14."),
            ({"numbers": "1, 2"}, "numbers: anna lista."),
            ({"numbers": [1, "kaksi"]}, "numbers: anna lista, jossa on vain numeroita."),
            ({"tags": [1]}, "tags: anna lista, jossa on vain tekstejä."),
            ({"done": "kyllä"}, "done: anna true tai false."),
            ({"options": []}, "options: anna objekti."),
        ]:
            with self.subTest(values=values), self.assertRaises(InputError) as raised:
                self.check(values)
            self.assertEqual(str(raised.exception), message)

    def test_checks_whole_numbers(self):
        self.assertEqual(self.module.handle({"action": "list", "limit": 5.0})["limit"], 5)
        for limit in (5.5, "5", True, None):
            with self.subTest(limit=limit), self.assertRaisesRegex(InputError, "^limit: anna kokonaisluku.$"):
                self.module.handle({"action": "list", "limit": limit})

    def test_a_value_without_a_default_is_required(self):
        with self.assertRaisesRegex(InputError, "^id: pakollinen tieto.$"):
            self.module.handle({"action": "get"})

    def test_labels_come_from_the_form_in_module_json(self):
        with tempfile.TemporaryDirectory() as directory:
            Path(directory, "module.json").write_text(json.dumps({"name": "x", "form": [{"name": "id", "label": "Uutisen numero"}]}))
            with mock.patch.object(Module, "directory", Path(directory)):
                with self.assertRaisesRegex(InputError, "^Uutisen numero: pakollinen tieto.$"):
                    self.module.handle({"action": "get"})

    def test_an_unsupported_type_hint_is_a_programming_error(self):
        class Odd(Module):
            @action("odd", default=True)
            def odd(self, value: set = frozenset()):
                return {}

        with self.assertRaises(TypeError):
            Odd().handle({"value": [1]})


class RunTest(unittest.TestCase):
    def setUp(self):
        self.module = NewsModule()

    def test_prints_the_output_as_json(self):
        code, stdout, stderr = run(self.module, '{"action": "list", "tag": "sää"}')

        self.assertEqual(code, 0)
        self.assertEqual(stdout, '{"tag": "sää", "limit": 20}')
        self.assertEqual(stderr, "")

    def test_an_input_error_is_the_message_on_stderr(self):
        self.assertEqual(run(self.module, '{"action": "get", "id": 2}'), (1, "", "Uutista ei löytynyt.\n"))

    def test_bad_json_is_an_input_error(self):
        self.assertEqual(run(self.module, "{"), (1, "", "Syöte ei ole kelvollista JSONia.\n"))

    def test_other_errors_end_with_a_generic_message_after_the_traceback(self):
        for name in ("broken", "not-a-dict"):
            with self.subTest(name=name):
                code, stdout, stderr = run(self.module, json.dumps({"action": name}))

                self.assertEqual((code, stdout), (1, ""))
                self.assertIn("Traceback", stderr)
                self.assertEqual(stderr.splitlines()[-1], "Moduulissa tapahtui virhe.")


class HelpersTest(unittest.TestCase):
    def test_the_module_folder_and_module_json(self):
        with tempfile.TemporaryDirectory() as directory:
            Path(directory, "module.json").write_text('{"name": "Uutiset"}')
            with mock.patch.object(Module, "directory", Path(directory)):
                self.assertEqual(NewsModule().manifest, {"name": "Uutiset"})

        self.assertEqual(NewsModule().directory, Path(__file__).resolve().parent)

    def test_the_data_folder(self):
        with mock.patch.dict(os.environ, {"JALAOPS_DATA_DIR": "/data/news"}):
            self.assertEqual(NewsModule().data_dir, Path("/data/news"))

        with mock.patch.dict(os.environ, clear=True), self.assertRaisesRegex(RuntimeError, '"storage": true'):
            NewsModule().data_dir

    def test_feed_items_leave_out_empty_values(self):
        self.assertEqual(feed_item("Syyshuollot alkavat"), {"title": "Syyshuollot alkavat"})
        self.assertEqual(
            feed_item("Uusi", text="Teksti", date="2026-10-20", tags=["huolto"], params={"news": "3"}),
            {"title": "Uusi", "text": "Teksti", "date": "2026-10-20", "tags": ["huolto"], "params": {"news": "3"}},
        )


if __name__ == "__main__":
    unittest.main()
