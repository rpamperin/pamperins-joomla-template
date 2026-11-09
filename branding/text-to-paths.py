#!/usr/bin/env python3
"""
Convert <text> in logo SVGs into vector <path>s using the Inter font, so logos
look identical on every device (an SVG shown with <img> can't use web fonts).

  pip install fonttools brotli uharfbuzz
  python3 branding/text-to-paths.py branding/original/*.svg --out branding/outlined

Supports what the logos use: x, y, font-size, font-weight, fill, letter-spacing,
text-anchor (start/middle/end) and <tspan fill="..."> runs.
"""
import argparse
import io
import pathlib
import re
import xml.etree.ElementTree as ET

import uharfbuzz as hb
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont

SVG_NS = "http://www.w3.org/2000/svg"
ET.register_namespace("", SVG_NS)
ROOT = pathlib.Path(__file__).resolve().parent.parent
FONT = ROOT / "template/media/fonts/inter-latin-wght-normal.woff2"


def load_font():
    tt = TTFont(str(FONT))
    tt.flavor = None  # woff2 -> plain sfnt bytes for HarfBuzz
    buf = io.BytesIO()
    tt.save(buf)
    face = hb.Face(buf.getvalue())
    return face


def num(v, default=0.0):
    if v is None:
        return default
    return float(re.sub(r"[^0-9.\-]", "", v) or default)


def text_runs(el):
    """[(string, fill)] from a <text> and its <tspan> children."""
    base_fill = el.get("fill", "#000")
    runs = []
    if el.text:
        runs.append((el.text, base_fill))
    for child in el:
        if child.tag == f"{{{SVG_NS}}}tspan":
            runs.append((child.text or "", child.get("fill", base_fill)))
        if child.tail:
            runs.append((child.tail, base_fill))
    # collapse whitespace like SVG's default xml:space handling
    out, prev_space = [], True
    for s, f in runs:
        s = re.sub(r"\s+", " ", s)
        if prev_space:
            s = s.lstrip()
        if s:
            out.append([s, f])
            prev_space = s.endswith(" ")
    if out:
        out[-1][0] = out[-1][0].rstrip()
    return [tuple(r) for r in out if r[0]]


def outline_text(el, face):
    size = num(el.get("font-size"), 16)
    weight = num(el.get("font-weight"), 400)
    spacing = num(el.get("letter-spacing"), 0)
    x0, y0 = num(el.get("x")), num(el.get("y"))
    anchor = el.get("text-anchor", "start")

    font = hb.Font(face)
    font.set_variations({"wght": weight})
    upem = face.upem
    scale = size / upem

    runs = text_runs(el)
    full = "".join(s for s, _ in runs)
    run_of_char = []
    for i, (s, _) in enumerate(runs):
        run_of_char += [i] * len(s)

    buf = hb.Buffer()
    buf.add_str(full)
    buf.guess_segment_properties()
    hb.shape(font, buf, {"kern": True, "liga": True})

    glyphs, pen_x = [], 0.0
    for info, pos in zip(buf.glyph_infos, buf.glyph_positions):
        glyphs.append((info.codepoint, info.cluster, pen_x + pos.x_offset * scale, pos.y_offset * scale))
        pen_x += pos.x_advance * scale + spacing
    width = pen_x - spacing

    if anchor == "middle":
        x0 -= width / 2
    elif anchor == "end":
        x0 -= width

    paths = {}
    for gid, cluster, gx, gy in glyphs:
        run = run_of_char[cluster]
        svg_pen = paths.setdefault(run, SVGPathPen(None, ntos=lambda v: f"{v:.2f}".rstrip("0").rstrip(".")))
        tpen = TransformPen(svg_pen, (scale, 0, 0, -scale, x0 + gx, y0 - gy))
        font.draw_glyph_with_pen(gid, tpen)

    out = []
    for run, pen in sorted(paths.items()):
        d = pen.getCommands()
        if d:
            p = ET.Element(f"{{{SVG_NS}}}path", {"d": d, "fill": runs[run][1]})
            out.append(p)
    return out


def convert(src: pathlib.Path, dst: pathlib.Path, face):
    tree = ET.parse(src)
    root = tree.getroot()

    def walk(parent):
        for i, child in enumerate(list(parent)):
            if child.tag == f"{{{SVG_NS}}}text":
                label = "".join(s for s, _ in text_runs(child))
                g = ET.Element(f"{{{SVG_NS}}}g", {"aria-label": label})
                for p in outline_text(child, face):
                    g.append(p)
                parent.remove(child)
                parent.insert(i, g)
            else:
                walk(child)

    walk(root)
    dst.parent.mkdir(parents=True, exist_ok=True)
    tree.write(dst, encoding="unicode", xml_declaration=False)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("files", nargs="+")
    ap.add_argument("--out", required=True)
    a = ap.parse_args()
    face = load_font()
    for f in a.files:
        src = pathlib.Path(f)
        dst = pathlib.Path(a.out) / src.name
        convert(src, dst, face)
        print(f"{src} -> {dst}")


if __name__ == "__main__":
    main()
