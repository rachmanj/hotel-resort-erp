"""Analisa pemakaian setData({...}) di resources/js (dipakai oleh docs/todo-setdata-audit.md): mana yang mengganti seluruh form data (kehilangan field) dan mana yang memang reset penuh.

Heuristik: untuk tiap panggilan <form>.setData({...}) kita cari deklarasi useForm({...}) pada file yang sama,
ambil daftar key-nya (termasuk key dari spread objek konstanta seperti ...defaultForm), lalu bandingkan dengan
key yang dikirim di objek setData. Key yang tidak ada di objek setData = berpotensi hilang.
"""
import re
import json
from pathlib import Path

ROOT = Path("resources/js")


def find_use_form_keys(text: str) -> dict[str, set[str]]:
    """Kembalikan {nama_variabel_form: set(key)} dari useForm({...}) (best effort)."""
    out: dict[str, set[str]] = {}
    for m in re.finditer(r"const\s+(\w+)\s*=\s*useForm\s*\(\s*\{", text):
        name = m.group(1)
        start = m.end() - 1
        depth = 0
        i = start
        while i < len(text):
            ch = text[i]
            if ch in "{[":
                depth += 1
            elif ch in "}]":
                depth -= 1
                if depth == 0:
                    break
            i += 1
        block = text[start : i + 1]
        keys = set()
        for km in re.finditer(r"^\s{8}(\w+)\s*:", block, re.M):
            keys.add(km.group(1))
        for km in re.finditer(r"^\s{4}(\w+)\s*:", block, re.M):
            keys.add(km.group(1))
        for sm in re.finditer(r"\.\.\.([\w.]+)", block):
            keys.add(f"...{sm.group(1)}")
        out[name] = keys
    return out


def const_object_keys(text: str, name: str) -> set[str]:
    m = re.search(rf"const\s+{name}\s*(?::[^=]+)?=\s*\{{", text)
    if not m:
        return set()
    start = m.end() - 1
    depth = 0
    i = start
    while i < len(text):
        if text[i] in "{[":
            depth += 1
        elif text[i] in "}]":
            depth -= 1
            if depth == 0:
                break
        i += 1
    block = text[start : i + 1]
    return set(re.findall(r"(\w+)\s*:", block))


def object_keys(text: str, start_index: int) -> tuple[set[str], list[str]]:
    """Ambil key dari objek literal yang mulai di start_index ('{' atau '{ ...')."""
    depth = 0
    i = start_index
    while i < len(text):
        if text[i] in "{[":
            depth += 1
        elif text[i] in "}]":
            depth -= 1
            if depth == 0:
                break
        i += 1
    block = text[start_index : i + 1]
    keys = set(re.findall(r"(\w+)\s*:", block))
    spreads = re.findall(r"\.\.\.([\w.]+)", block)
    return keys, spreads


def enclosing_function(text: str, index: int) -> str:
    head = text[:index]
    m = None
    for m2 in re.finditer(r"const\s+(\w+)\s*=\s*\([^)]*\)\s*=>|const\s+(\w+)\s*=\s*function|function\s+(\w+)\s*\(", head):
        m = m2
    if not m:
        return "(top level)"
    return next(g for g in m.groups() if g)


results = []
for path in sorted(ROOT.rglob("*.tsx")):
    text = path.read_text(encoding="utf-8")
    forms = find_use_form_keys(text)
    consts = {name: const_object_keys(text, name) for name in re.findall(r"const\s+(\w+)\s*=", text)}
    for m in re.finditer(r"(\w+)\.setData\(\s*\{", text):
        var = m.group(1)
        line = text[: m.start()].count("\n") + 1
        brace = text.index("{", m.start())
        keys, spreads = object_keys(text, brace)
        spread_keys: set[str] = set()
        merges_full = False
        for s in spreads:
            if s.endswith(".data"):
                merges_full = True
            if s in forms:
                spread_keys |= forms[s]
            elif s in consts:
                spread_keys |= consts[s]
        expected = forms.get(var, set())
        expected_named = {k for k in expected if not k.startswith("...")}
        if merges_full:
            verdict = "aman (merge form.data)"
            missing: set[str] = set()
        else:
            missing = expected_named - keys - spread_keys
            verdict = "RESET penuh (disengaja?)" if not missing else f"KEHILANGAN {len(missing)} field"
        results.append(
            {
                "file": str(path.relative_to(ROOT)),
                "line": line,
                "form": var,
                "handler": enclosing_function(text, m.start()),
                "form_keys": len(expected_named),
                "object_keys": len(keys),
                "missing": sorted(missing),
                "verdict": verdict,
            }
        )

summary = {
    "total": len(results),
    "hilang": [r for r in results if r["verdict"].startswith("KEHILANGAN")],
    "reset": [r for r in results if r["verdict"].startswith("RESET")],
    "aman": [r for r in results if r["verdict"].startswith("aman")],
}
print(json.dumps(summary, indent=2)[:6000])
Path("/tmp/setdata_audit.json").write_text(json.dumps(results, indent=2), encoding="utf-8")
print("\nfile-files dengan KEHILANGAN:")
for r in summary["hilang"]:
    print(f"  {r['file']}:{r['line']} {r['handler']} -> hilang: {', '.join(r['missing'])}")
