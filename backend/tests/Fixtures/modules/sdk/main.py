# A module that uses the module SDK from outside backend/modules, so it shows
# that ModuleRunner puts the SDK on PYTHONPATH wherever a module is.
from jalaops_module import InputError, Module, action


class FixtureModule(Module):
    @action("list", default=True)
    def list_items(self, limit: int = 2):
        return {"items": list(range(limit))}

    @action("get")
    def get_item(self, id: int):
        if id > 9:
            raise InputError("Ei löytynyt.")
        return {"id": id}

    @action("crash")
    def crash(self):
        raise RuntimeError("secret detail")


if __name__ == "__main__":
    FixtureModule.main()
