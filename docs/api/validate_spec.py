"""Offline OpenAPI meta-schema and synthetic-example checks; no app imports."""

import argparse
from copy import deepcopy
import hashlib
import importlib.metadata
import json
from pathlib import Path

import jsonschema


HERE = Path(__file__).resolve().parent
OFFICIAL_SCHEMA_SHA256 = "2385f5bbb8c37878daae73baeabe7f34b2f022a4a8c049329ee61f71796f039c"


def json_schema(value):
    """Adapt OAS 3.0 nullable to Draft 4 for local synthetic data checks only."""
    if isinstance(value, list):
        return [json_schema(item) for item in value]
    if not isinstance(value, dict):
        return value
    result = {key: json_schema(item) for key, item in value.items()}
    if result.pop("nullable", False) and "type" in result:
        result["type"] = [result["type"], "null"]
    return result


def validate_example(spec, schema_name, example):
    document = {"$ref": "#/components/schemas/" + schema_name, "components": json_schema(spec["components"])}
    return list(jsonschema.Draft4Validator(document, format_checker=jsonschema.FormatChecker()).iter_errors(example))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--meta-schema", required=True, type=Path,
                        help="Cached official OAS 3.0 schema 2024-10-18; script never downloads files.")
    args = parser.parse_args()
    raw = args.meta_schema.read_bytes()
    if hashlib.sha256(raw).hexdigest() != OFFICIAL_SCHEMA_SHA256:
        raise SystemExit("Unexpected meta-schema fingerprint; verify official source/version before using it.")
    meta = json.loads(raw)
    spec = json.loads((HERE / "openapi.json").read_text(encoding="utf-8"))
    jsonschema.Draft4Validator.check_schema(meta)
    errors = list(jsonschema.Draft4Validator(meta, format_checker=jsonschema.FormatChecker()).iter_errors(spec))
    if errors:
        for error in errors:
            print("/".join(map(str, error.absolute_path)), error.message)
        raise SystemExit("FAIL: official OpenAPI meta-schema validation")
    examples = 0
    for name, schema in spec["components"]["schemas"].items():
        if "example" not in schema:
            continue
        found = validate_example(spec, name, schema["example"])
        if found:
            raise SystemExit(f"FAIL: synthetic {name} example: {found[0].message}")
        examples += 1
    negatives = [
        ("WasteRecordInput", lambda item: item.pop("quantity"), "required quantity"),
        ("WasteRecordInput", lambda item: item.update(quantity=-1), "negative record quantity"),
        ("WasteHaulingInput", lambda item: item.update(quantity=0), "zero hauling quantity"),
        ("FabaProductionInput", lambda item: item.update(movement_type="not_a_type"), "invalid movement type"),
        ("FabaAdjustmentInput", lambda item: item.update(note=""), "empty adjustment note"),
        ("FabaSubmitPeriodInput", lambda item: item.update(month=13), "invalid month"),
        ("WasteRejectionInput", lambda item: item.update(rejection_reason="short"), "short rejection reason"),
        ("FabaTpsCapacityInput", lambda item: item.update(warning_threshold=100), "warning threshold boundary"),
    ]
    for name, mutate, label in negatives:
        example = deepcopy(spec["components"]["schemas"][name]["example"])
        mutate(example)
        if not validate_example(spec, name, example):
            raise SystemExit(f"FAIL: static negative fixture accepted: {label}")
    print(f"PASS: official OAS 3.0 meta-schema; {examples} synthetic schema examples; {len(negatives)} negative schema fixtures.")
    print(f"Installed jsonschema {importlib.metadata.version('jsonschema')}; no dependency installation.")
    print("This validates the specification and synthetic fixtures, not server behavior. No HTTP, DB, auth/security operations or Laravel bootstrap.")


if __name__ == "__main__":
    main()
