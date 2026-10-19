import json
import sys

json.dump({"message": "Hei paketista!"}, sys.stdout, ensure_ascii=False)
