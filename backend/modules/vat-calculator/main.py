"""VAT calculator module for JalaOps: net price, VAT and gross price.

Input:  {"amount": 100, "rate": "25.5"}   (rate optional, default 25.5 %)
Output: {"net": 100.0, "rate": 25.5, "vat": 25.5, "total": 125.5}

Amounts are rounded to cents like on an invoice (half up).
"""

import json
import sys
from decimal import ROUND_HALF_UP, Decimal, InvalidOperation

# Finnish VAT rates (%).
RATES = {"25.5", "14", "10", "0"}
CENT = Decimal("0.01")


def fail(message):
    """Ends with an error; the app shows the message to the user."""
    print(message, file=sys.stderr)
    sys.exit(1)


def main():
    data = json.load(sys.stdin)

    amount = data.get("amount")
    if not isinstance(amount, (int, float)) or isinstance(amount, bool):
        fail("Anna veroton hinta numerona.")

    rate = str(data.get("rate", "25.5"))
    if rate not in RATES:
        fail("ALV-kannan pitää olla 25.5, 14, 10 tai 0 %.")

    try:
        net = Decimal(str(amount)).quantize(CENT, ROUND_HALF_UP)
    except InvalidOperation:
        fail("Anna veroton hinta numerona.")

    if net < 0 or net > Decimal("1000000000"):
        fail("Verottoman hinnan pitää olla 0–1 000 000 000 €.")

    vat = (net * Decimal(rate) / 100).quantize(CENT, ROUND_HALF_UP)

    json.dump(
        {"net": float(net), "rate": float(rate), "vat": float(vat), "total": float(net + vat)},
        sys.stdout,
    )


if __name__ == "__main__":
    main()
