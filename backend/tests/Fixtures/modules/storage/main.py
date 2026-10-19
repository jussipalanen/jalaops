# Counts its runs in a file in its data folder, and returns a feed for its widget.
import json
import os
import sys

data = json.load(sys.stdin)

if data.get("action") == "feed":
    json.dump({"items": [
        {"title": "Ensimmäinen", "text": "Teksti", "date": "2026-10-19T12:00:00Z", "image": "data:image/png;base64,iVBORw0KGgo=",
         "tags": ["a", 5], "params": {"news": 3, "Bad-Key": "x", "list": [1]}, "extra": "dropped"},
        {"title": "Toinen", "image": "javascript:alert(1)", "date": "not a date"},
    ]}, sys.stdout)
    sys.exit()

path = os.path.join(os.environ["JALAOPS_DATA_DIR"], "count.txt")
count = int(open(path).read()) + 1 if os.path.exists(path) else 1
open(path, "w").write(str(count))

json.dump({"count": count, "directory": os.environ["JALAOPS_DATA_DIR"], "size": len(data.get("text", "")), "text": data.get("text")}, sys.stdout)
