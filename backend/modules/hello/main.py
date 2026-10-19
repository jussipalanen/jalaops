"""Hello world module for JalaOps.

Greets and does a small calculation, to show how Laravel runs Python modules:
a JSON object comes in on stdin and a JSON object goes out on stdout.

Input (both optional):
    {"name": "Maailma", "numbers": [3, 5, 10]}

Output:
    {"message": "Hei, Maailma!", "count": 3, "sum": 18, "average": 6,
     "median": 5, "python": "3.13.5"}

Try it: ./dev artisan modules:run hello '{"name": "Maailma", "numbers": [3, 5, 10]}'
"""

import json
import platform
import statistics
import sys


def fail(message):
    """Ends with an error; Laravel shows stderr in its error message."""
    print(message, file=sys.stderr)
    sys.exit(1)


def main():
    try:
        data = json.load(sys.stdin)
    except json.JSONDecodeError:
        fail("Syöte ei ole kelvollista JSONia.")

    if not isinstance(data, dict):
        fail("Syötteen pitää olla JSON-objekti.")

    name = data.get("name", "Maailma")
    numbers = data.get("numbers", [])

    if not isinstance(name, str) or not name.strip():
        fail("Nimen pitää olla tekstiä.")

    # bool is a subclass of int in Python, so true and false are ruled out separately.
    if not isinstance(numbers, list) or not all(
        isinstance(number, (int, float)) and not isinstance(number, bool) for number in numbers
    ):
        fail("Lukujen pitää olla lista numeroita.")

    result = {"message": f"Hei, {name.strip()}!", "count": len(numbers)}

    if numbers:
        result["sum"] = sum(numbers)
        result["average"] = round(statistics.mean(numbers), 2)
        result["median"] = statistics.median(numbers)

    result["python"] = platform.python_version()

    json.dump(result, sys.stdout, ensure_ascii=False)


if __name__ == "__main__":
    main()
