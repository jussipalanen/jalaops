import json
import sys

data = json.load(sys.stdin)
json.dump({"city": data.get("city", "Helsinki"), "forecast": "Aurinkoista"}, sys.stdout, ensure_ascii=False)
