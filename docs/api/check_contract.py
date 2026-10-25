"""Read-only static checks; standard library only. Does not bootstrap Laravel."""

import hashlib
import json
from pathlib import Path
import re


ROOT = Path(__file__).resolve().parents[2]
SPEC = Path(__file__).with_name("openapi.json")
METHODS = {"get", "post", "put", "patch", "delete"}
SERIALIZERS = [
    ("WasteRecord", "WasteRecordController", "serializeWasteRecord"),
    ("WasteBacklogRecord", "WasteHaulingController", "serializeBacklogRecord"),
    ("WasteHauling", "WasteHaulingController", "serializeHauling"),
    ("FabaProductionMovement", "FabaProductionController", "serializeMovement"),
    ("FabaAdjustmentMovement", "FabaAdjustmentController", "serializeMovement"),
    ("FabaUtilizationMovement", "FabaUtilizationController", "serializeMovement"),
]
REQUESTS = [
    ("WasteRecordInput", "WasteRecordRequest", set()),
    ("WasteHaulingInput", "WasteHaulingRequest", set()),
    ("FabaProductionInput", "FabaProductionMovementRequest", set()),
    ("FabaAdjustmentInput", "FabaAdjustmentRequest", set()),
    ("FabaUtilizationFieldsPartial", "FabaUtilizationMovementRequest", {"attachment"}),
    ("WasteApprovalInput", "ApproveWasteRecordRequest", set()),
    ("WasteApprovalInput", "ApproveWasteHaulingRequest", set()),
    ("WasteRejectionInput", "RejectWasteRecordRequest", set()),
    ("WasteRejectionInput", "RejectWasteHaulingRequest", set()),
    ("FabaApprovalInput", "ApproveFabaMonthlyApprovalRequest", set()),
    ("FabaRejectionInput", "RejectFabaMonthlyApprovalRequest", set()),
    ("FabaReopenInput", "ReopenFabaMonthlyApprovalRequest", set()),
    ("FabaSubmitPeriodInput", "SubmitFabaMonthlyApprovalRequest", set()),
    ("FabaOpeningBalanceInput", "StoreFabaOpeningBalanceRequest", set()),
    ("FabaTpsCapacityInput", "StoreFabaTpsCapacityRequest", set()),
]


def literal_fields(source, method, assignment="return ["):
    """Bounded textual check for current Pint-formatted literal arrays only."""
    text = (ROOT / source).read_text(encoding="utf-8")
    start = re.search(r"function\s+" + re.escape(method) + r"\s*\(", text)
    assert start, f"Missing method {source}::{method}"
    body = re.split(r"\n    (?:public|protected|private) (?:static )?function ", text[start.end():], maxsplit=1)[0]
    assert assignment in body, f"Unsupported array syntax: {source}::{method}"
    literal = body.split(assignment, 1)[1].split("\n        ];", 1)[0]
    fields = re.findall(r"^            '([^']+)'\s*=>", literal, re.MULTILINE)
    assert fields and len(fields) == len(set(fields)), f"Ambiguous fields: {source}::{method}"
    return set(fields)


def read_routes():
    """Parse the current explicit route declarations, failing on unknown syntax."""
    stack = []
    routes = []
    for number, raw in enumerate((ROOT / "routes/api.php").read_text(encoding="utf-8").splitlines(), 1):
        line = raw.strip()
        prefix = re.match(r"Route::prefix\('([^']+)'\)->group", line)
        if prefix:
            stack.append(prefix[1])
            continue
        if re.match(r"Route::middleware\(.+->group", line):
            stack.append("")
            continue
        if line == "});":
            assert stack, f"Unexpected group close: {number}"
            stack.pop()
            continue
        match = re.fullmatch(
            r"Route::(get|post|put|patch|delete)\('([^']*)', (.+)\)->name\('([^']+)'\);",
            line,
        )
        if not match:
            assert not line.startswith("Route::"), f"Unsupported declaration: {number}"
            continue
        method, suffix, handler, name = match.groups()
        full = "/api/" + "/".join(part for part in stack if part)
        full += "" if suffix == "/" else suffix
        assert full.startswith("/api/v1/"), full
        action = re.fullmatch(r"\[([A-Za-z]+)::class, '([^']+)'\]", handler)
        if action:
            controller, function = action.groups()
        else:
            invoke = re.fullmatch(r"([A-Za-z]+)::class", handler)
            assert invoke, f"Unsupported handler: {number}"
            controller, function = invoke[1], "__invoke"
        source = f"app/Http/Controllers/Api/{controller}.php"
        assert re.search(r"function\s+" + re.escape(function) + r"\s*\(", (ROOT / source).read_text(encoding="utf-8"))
        routes.append({"method": method, "path": full.removeprefix("/api/v1"),
                       "name": name, "controller": controller, "action": function,
                       "source": source, "line": number})
    assert not stack, "Unclosed route group"
    return routes


def walk(value):
    if isinstance(value, dict):
        yield value
        for child in value.values():
            yield from walk(child)
    elif isinstance(value, list):
        for child in value:
            yield from walk(child)


def main():
    spec = json.loads(SPEC.read_text(encoding="utf-8"))
    assert spec["openapi"] == "3.0.3"
    assert spec["servers"] == [{"url": "/api/v1"}]
    assert spec["x-contract-status"] == "partial-source-inventory-not-frozen"
    routes = read_routes()
    expected = {(route["path"], route["method"]): route for route in routes}
    assert len(expected) == len(routes) == 61, "Duplicate or changed route count"
    assert len({route["name"] for route in routes}) == len(routes)
    actual = {(path, method): operation for path, item in spec["paths"].items()
              for method, operation in item.items() if method in METHODS}
    assert actual.keys() == expected.keys(), "Spec/source method-path mismatch"
    ids = []
    for key, operation in actual.items():
        route = expected[key]
        ids.append(operation["operationId"])
        assert operation["operationId"] == route["name"].replace(".", "_")
        assert operation["x-route-name"] == route["name"]
        assert operation["x-source"]["controller"] == route["source"]
        assert operation["x-source"]["action"] == route["action"]
        assert operation["x-source"]["route-line"] == route["line"]
        placeholders = set(re.findall(r"\{([^}]+)\}", key[0]))
        parameters = [p for p in operation.get("parameters", []) if p.get("in") == "path"]
        assert {p["name"] for p in parameters} == placeholders
        assert len(parameters) == len(placeholders)
        assert all(p["required"] is True for p in parameters)
        assert operation.get("x-contract-gaps"), f"Missing partial-contract warning: {key}"
        assert operation["responses"].get("default"), f"Missing unknown-response fallback: {key}"
    assert len(set(ids)) == len(ids), "Duplicate operationId"
    references = 0
    for item in walk(spec):
        if "$ref" not in item:
            continue
        ref = item["$ref"]
        assert ref.startswith("#/"), f"External reference not allowed: {ref}"
        target = spec
        for part in ref[2:].split("/"):
            target = target[part.replace("~1", "/").replace("~0", "~")]
        references += 1
    assert spec["x-source-hash-format"] == "sha256-utf8-lf"
    for source, digest in spec["x-source-sha256"].items():
        normalized = (ROOT / source).read_text(encoding="utf-8").encode("utf-8")
        assert hashlib.sha256(normalized).hexdigest() == digest, f"Stale source fingerprint: {source}"
    paginated = [op for op in actual.values() if op.get("x-pagination") == "page"]
    assert len(paginated) == 6
    schemas = spec["components"]["schemas"]
    for name, controller, method in SERIALIZERS:
        source = f"app/Http/Controllers/Api/{controller}.php"
        assert literal_fields(source, method) == set(schemas[name]["properties"]), f"Serializer fields drifted: {name}"
    for name, form, deferred in REQUESTS:
        source = f"app/Http/Requests/WasteManagement/{form}.php"
        assignment = "$rules = [" if form == "WasteRecordRequest" else "return ["
        assert literal_fields(source, "rules", assignment) - deferred == set(schemas[name]["properties"]), f"Request fields drifted: {name}/{form}"
        assert source in spec["x-source-sha256"], f"Missing request fingerprint: {source}"
    print(f"PASS: {len(routes)} operations / {len(spec['paths'])} paths; unique operationIds; "
          f"path parameters; {references} resolved local references; "
          f"{len(spec['x-source-sha256'])} source fingerprints; 6 pagination operations; "
          f"{len(SERIALIZERS)} serializer and {len(REQUESTS)} Form Request field sets.")
    print("Source consistency only; validate_spec.py performs separate official meta-schema/example checks. No HTTP, DB, Laravel bootstrap or integration tests executed.")


if __name__ == "__main__":
    main()
