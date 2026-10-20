"""VAT calculator module for JalaOps: net price, VAT and gross price.

Input:  {"amount": 100, "rate": "25.5"}   (rate optional, default 25.5 %)
Output: {"net": 100.0, "rate": 25.5, "vat": 25.5, "total": 125.5}

Amounts are rounded to cents like on an invoice (half up). It uses the module
SDK (modules/sdk/jalaops_module.py): the type hints check the input, and a
Literal allows only the Finnish VAT rates.
"""

from decimal import ROUND_HALF_UP, Decimal
from typing import Literal

from jalaops_module import InputError, Module, action

CENT = Decimal("0.01")


class VatCalculatorModule(Module):
    @action("calculate", default=True)
    def calculate(self, amount: float, rate: Literal["25.5", "14", "10", "0"] = "25.5"):
        net = Decimal(str(amount)).quantize(CENT, ROUND_HALF_UP)

        if net < 0 or net > Decimal("1000000000"):
            raise InputError("Verottoman hinnan pitää olla 0–1 000 000 000 €.")

        vat = (net * Decimal(rate) / 100).quantize(CENT, ROUND_HALF_UP)

        return {"net": float(net), "rate": float(rate), "vat": float(vat), "total": float(net + vat)}


if __name__ == "__main__":
    VatCalculatorModule.main()
