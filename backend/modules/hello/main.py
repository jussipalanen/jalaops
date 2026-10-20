"""Hello world module for JalaOps.

Greets and does a small calculation, to show how Laravel runs Python modules:
a JSON object comes in on stdin and a JSON object goes out on stdout. It uses
the module SDK (modules/sdk/jalaops_module.py), which reads the input, checks
it against the method's type hints and prints the output.

Input (both optional):
    {"name": "Maailma", "numbers": [3, 5, 10]}

Output:
    {"message": "Hei, Maailma!", "count": 3, "sum": 18, "average": 6,
     "median": 5, "python": "3.13.5"}

Try it: ./dev artisan modules:run hello '{"name": "Maailma", "numbers": [3, 5, 10]}'
"""

import platform
import statistics

from jalaops_module import InputError, Module, action


class HelloModule(Module):
    # The default action: the input needs no "action". Error messages use
    # the form's labels in module.json ("Luvut: anna lista, jossa…").
    @action("greet", default=True)
    def greet(self, name: str = "Maailma", numbers: list[float] | None = None):
        if not name.strip():
            raise InputError("Nimi: pakollinen tieto.")

        numbers = numbers or []
        result = {"message": f"Hei, {name.strip()}!", "count": len(numbers)}

        if numbers:
            result["sum"] = sum(numbers)
            result["average"] = round(statistics.mean(numbers), 2)
            result["median"] = statistics.median(numbers)

        result["python"] = platform.python_version()

        return result


if __name__ == "__main__":
    HelloModule.main()
