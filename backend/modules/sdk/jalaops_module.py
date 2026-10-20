"""A base class for JalaOps modules: actions, input checks and Finnish errors.

JalaOps puts this folder on the module's PYTHONPATH, so every module can use
it without installing anything:

    from jalaops_module import InputError, Module, action

    class NewsFeedModule(Module):
        @action("list", default=True)
        def list_news(self, tag: str | None = None, limit: int = 20):
            return {"items": [...]}

        @action("get")
        def get_news(self, id: int):
            if id not in NEWS:
                raise InputError("Uutista ei löytynyt.")
            return {"news": NEWS[id]}

    if __name__ == "__main__":
        NewsFeedModule.main()

main() reads the JSON object on stdin, picks the action by its "action" (or
the default one), checks the other values against the method's type hints
and prints the returned object as JSON. An InputError becomes the Finnish
message JalaOps shows to the user. Any other error becomes a generic message,
with the traceback on stderr for the backend's log.

Using the class is optional: a plain main.py that reads stdin and writes
stdout keeps working. The class isn't a security boundary either; the module
still runs as its own process without the backend's secrets.
"""

import inspect
import json
import os
import sys
import traceback
import types
import typing
from pathlib import Path

__all__ = ["InputError", "Module", "action", "feed_item"]

GENERIC_ERROR = "Moduulissa tapahtui virhe."


class InputError(Exception):
    """A problem with the input; the message is shown to the user in Finnish."""


def action(name, default=False, labels=None):
    """Marks a method as an action the input can pick with {"action": name}.

    default: the action to run when the input has no "action".
    labels: the names users see in error messages, by parameter. Without
        one, the label of the form field with the same name in module.json
        is used, and then the parameter's own name.
    """

    def mark(method):
        method._jalaops_action = {"name": name, "default": default, "labels": labels or {}}
        return method

    return mark


def feed_item(title, text=None, date=None, image=None, tags=None, params=None):
    """One item of a "feed" widget's output: {"items": [feed_item(...), ...]}.

    Only title is required; values left as None are left out.
    """
    item = {"title": title, "text": text, "date": date, "image": image, "tags": tags, "params": params}
    return {key: value for key, value in item.items() if value is not None}


class Module:
    """The base class of a module. Its actions are methods marked with @action."""

    def __init_subclass__(cls, **kwargs):
        super().__init_subclass__(**kwargs)
        # An action called run or handle would replace this class's own method.
        for name, value in vars(cls).items():
            if hasattr(value, "_jalaops_action") and hasattr(Module, name):
                raise TypeError(f"The action method {name}() would replace Module.{name}(); give it another name.")

    @classmethod
    def main(cls):
        """Runs the module as JalaOps does: JSON on stdin, JSON on stdout."""
        sys.exit(cls().run(sys.stdin, sys.stdout, sys.stderr))

    def run(self, stdin, stdout, stderr):
        """Handles one input and returns the exit code (0 when it worked)."""
        try:
            try:
                data = json.load(stdin)
            except (json.JSONDecodeError, UnicodeDecodeError):
                raise InputError("Syöte ei ole kelvollista JSONia.") from None
            output = self.handle(data)
        except InputError as error:
            print(str(error) or GENERIC_ERROR, file=stderr)
            return 1
        except Exception:
            # The traceback goes to the log; the last line is what users see.
            traceback.print_exc(file=stderr)
            print(GENERIC_ERROR, file=stderr)
            return 1

        json.dump(output, stdout, ensure_ascii=False)
        return 0

    def handle(self, data):
        """Runs the action the input picks and returns its output.

        Raises InputError when the input is wrong. Tests can call this
        directly with a dict.
        """
        if not isinstance(data, dict):
            raise InputError("Syötteen pitää olla JSON-objekti.")

        data = dict(data)
        name = data.pop("action", None)
        actions = self._actions()

        if name is None:
            method = next((method for method in actions.values() if method._jalaops_action["default"]), None)
            if method is None:
                raise InputError("Anna toiminto (action).")
        elif not isinstance(name, str) or name not in actions:
            raise InputError(f"Tuntematon toiminto: {name}.")
        else:
            method = actions[name]

        output = method(**self._arguments(method, data))

        if not isinstance(output, dict):
            raise TypeError(f"Action {method.__name__} must return a dict, not {type(output).__name__}.")

        return output

    @property
    def directory(self):
        """The module's folder: the one with its main.py and module.json."""
        return Path(inspect.getfile(type(self))).resolve().parent

    @property
    def manifest(self):
        """The module's module.json as a dict."""
        if not hasattr(self, "_manifest"):
            with open(self.directory / "module.json", encoding="utf-8") as file:
                self._manifest = json.load(file)
        return self._manifest

    @property
    def data_dir(self):
        """The module's data folder (JALAOPS_DATA_DIR), for "storage": true."""
        path = os.environ.get("JALAOPS_DATA_DIR")
        if not path:
            raise RuntimeError('JALAOPS_DATA_DIR is not set: add "storage": true to module.json.')
        return Path(path)

    def _actions(self):
        """The marked methods by action name, bound to this module."""
        actions = {}
        defaults = []

        for _, function in inspect.getmembers(type(self), inspect.isfunction):
            marked = getattr(function, "_jalaops_action", None)
            if marked is None:
                continue
            if marked["name"] in actions:
                raise TypeError(f"Two methods are the action {marked['name']!r}.")
            actions[marked["name"]] = getattr(self, function.__name__)
            if marked["default"]:
                defaults.append(marked["name"])

        if len(defaults) > 1:
            raise TypeError(f"Only one action can be the default, not {', '.join(defaults)}.")

        return actions

    def _arguments(self, method, data):
        """The method's arguments from the input, checked against its type hints."""
        hints = typing.get_type_hints(method)
        signature = inspect.signature(method)
        takes_rest = any(parameter.kind is inspect.Parameter.VAR_KEYWORD for parameter in signature.parameters.values())
        arguments = {}

        for name, parameter in signature.parameters.items():
            if parameter.kind in (inspect.Parameter.VAR_POSITIONAL, inspect.Parameter.VAR_KEYWORD):
                continue

            label = self._label(method, name)

            if name not in data:
                if parameter.default is inspect.Parameter.empty:
                    raise InputError(f"{label}: pakollinen tieto.")
                continue

            arguments[name] = check(data[name], hints.get(name, typing.Any), label)

        if takes_rest:
            arguments.update({key: value for key, value in data.items() if key not in signature.parameters})

        return arguments

    def _label(self, method, name):
        labels = method._jalaops_action["labels"]
        if name in labels:
            return labels[name]

        try:
            form = self.manifest.get("form", [])
        except (OSError, ValueError):
            form = []

        for field in form if isinstance(form, list) else []:
            if isinstance(field, dict) and field.get("name") == name and isinstance(field.get("label"), str):
                return field["label"]

        return name


def check(value, hint, label):
    """Checks one value against a type hint and returns it.

    A float also accepts whole numbers, which stay ints (as in Python's own
    type checks), and an int accepts a float like 5.0 as 5.
    """
    if hint is typing.Any or hint is inspect.Parameter.empty:
        return value

    origin = typing.get_origin(hint)
    options = typing.get_args(hint)

    # Optional values: X | None and typing.Optional[X].
    if origin in (typing.Union, types.UnionType):
        if value is None and type(None) in options:
            return None
        rest = [option for option in options if option is not type(None)]
        if len(rest) == 1:
            return check(value, rest[0], label)
        for option in rest:
            try:
                return check(value, option, label)
            except InputError:
                pass
        raise InputError(f"{label}: virheellinen arvo.")

    if origin is typing.Literal:
        # The type must match too: "14" isn't 14, and true isn't 1.
        if any(value == option and type(value) is type(option) for option in options):
            return value
        raise InputError(f"{label}: valitse jokin näistä: {', '.join(str(option) for option in options)}.")

    if hint is bool:
        if isinstance(value, bool):
            return value
        raise InputError(f"{label}: anna true tai false.")

    # bool is a subclass of int in Python, so true and false are ruled out separately.
    if hint is int:
        if isinstance(value, int) and not isinstance(value, bool):
            return value
        if isinstance(value, float) and value.is_integer():
            return int(value)
        raise InputError(f"{label}: anna kokonaisluku.")

    if hint is float:
        if isinstance(value, (int, float)) and not isinstance(value, bool):
            return value
        raise InputError(f"{label}: anna numero.")

    if hint is str:
        if isinstance(value, str):
            return value
        raise InputError(f"{label}: anna tekstiä.")

    if hint is list or origin is list:
        if not isinstance(value, list):
            raise InputError(f"{label}: anna lista.")
        if not options:
            return value
        try:
            return [check(item, options[0], label) for item in value]
        except InputError:
            raise InputError(f"{label}: anna lista, jossa {_kind(options[0])}.") from None

    if hint is dict or origin is dict:
        if isinstance(value, dict):
            return value
        raise InputError(f"{label}: anna objekti.")

    raise TypeError(f"jalaops_module can't check the type {hint!r} of {label}; use int, float, str, bool, list, dict, Literal or None.")


def _kind(hint):
    """What a list's items must be, in Finnish."""
    return {int: "on vain kokonaislukuja", float: "on vain numeroita", str: "on vain tekstejä", bool: "on vain true tai false"}.get(
        hint, "on vain sallittuja arvoja"
    )
