#!/usr/bin/env python3
"""Build rfc-001-kinetik.docx: swap mermaid blocks for pre-rendered PNGs,
generate a bordered reference docx, then run pandoc."""
import re, subprocess, zipfile, shutil, os, sys

HERE = os.path.dirname(os.path.abspath(__file__))
MD = os.path.join(HERE, "rfc-001-kinetik.md")
OUT = os.path.join(HERE, "rfc-001-kinetik.docx")
PROC = os.path.join(HERE, ".rfc-processed.md")
REF = os.path.join(HERE, ".reference.docx")

# mermaid block (in document order) -> (png, width inches) ; fit <=6in wide, <=8.5in tall
IMAGES = [
    ("diagrams/01-architecture-overview.png", 6.0),
    ("diagrams/02-alur-dokumen-rapat.png", 3.06),
    ("diagrams/03-pilar-b-drilldown.png", 5.56),
    ("diagrams/04-dfd-l1.png", 3.13),
    ("diagrams/05-sequence-sync.png", 6.0),
]

def process_md():
    src = open(MD, encoding="utf-8").read()
    blocks = list(re.finditer(r"```mermaid\n.*?\n```", src, re.DOTALL))
    if len(blocks) != len(IMAGES):
        sys.exit(f"mermaid block count {len(blocks)} != images {len(IMAGES)}")
    out, last, i = [], 0, 0
    for m in blocks:
        out.append(src[last:m.start()])
        png, w = IMAGES[i]
        out.append(f"![]({png}){{width={w}in}}")
        last, i = m.end(), i + 1
    out.append(src[last:])
    open(PROC, "w", encoding="utf-8").write("".join(out))

def build_reference():
    base = os.path.join(HERE, ".ref-base.docx")
    subprocess.run(["pandoc", "-o", base, "--print-default-data-file", "reference.docx"],
                   stdout=open(base, "wb"), check=True)
    tmp = os.path.join(HERE, ".ref-unzip")
    if os.path.exists(tmp): shutil.rmtree(tmp)
    with zipfile.ZipFile(base) as z: z.extractall(tmp)
    styles_path = os.path.join(tmp, "word", "styles.xml")
    xml = open(styles_path, encoding="utf-8").read()
    borders = ('<w:tblBorders>'
               '<w:top w:val="single" w:sz="4" w:space="0" w:color="999999"/>'
               '<w:left w:val="single" w:sz="4" w:space="0" w:color="999999"/>'
               '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="999999"/>'
               '<w:right w:val="single" w:sz="4" w:space="0" w:color="999999"/>'
               '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="999999"/>'
               '<w:insideV w:val="single" w:sz="4" w:space="0" w:color="999999"/>'
               '</w:tblBorders>')
    # inject into the Table style's tblPr
    m = re.search(r'(<w:style [^>]*w:styleId="Table"[^>]*>.*?<w:tblPr>)(.*?)(</w:tblPr>)', xml, re.DOTALL)
    if not m: sys.exit("Table style tblPr not found in reference styles.xml")
    xml = xml[:m.start()] + m.group(1) + borders + m.group(2) + m.group(3) + xml[m.end():]
    open(styles_path, "w", encoding="utf-8").write(xml)
    if os.path.exists(REF): os.remove(REF)
    with zipfile.ZipFile(REF, "w", zipfile.ZIP_DEFLATED) as z:
        for root, _, files in os.walk(tmp):
            for fn in files:
                full = os.path.join(root, fn)
                z.write(full, os.path.relpath(full, tmp))
    shutil.rmtree(tmp); os.remove(base)

def build():
    process_md(); build_reference()
    subprocess.run([
        "pandoc", PROC, "-o", OUT,
        "--reference-doc", REF,
        "--toc", "--toc-depth=2",
        "--resource-path", HERE,
        "-f", "markdown+pipe_tables",
    ], cwd=HERE, check=True)
    os.remove(PROC); os.remove(REF)
    print("built", OUT)

if __name__ == "__main__":
    build()
