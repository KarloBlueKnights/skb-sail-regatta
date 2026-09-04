#!/usr/bin/env python3
"""
Erzeugt changelog.html aus der Git-Commit-Historie dieses Repos.

Aufruf: python3 generate_changelog.py
Vor jedem Upload einmal laufen lassen. Commit-Konvention: kurze Titelzeile,
danach Stichpunkte im Body (git commit -m "Titel" -m "- Punkt 1" -m "- Punkt 2").
"""
import html
import subprocess
import sys
from datetime import datetime

SITE_NAME = "SKB Regatta-Zeitmessung"
GA_SEP = "\x1f"
GR_SEP = "\x1e"


def get_commits():
    fmt = f"%H{GA_SEP}%ad{GA_SEP}%s{GA_SEP}%b{GR_SEP}"
    out = subprocess.run(
        ["git", "log", "--no-merges", f"--pretty=format:{fmt}", "--date=format:%d.%m.%Y"],
        capture_output=True, text=True, check=True,
    ).stdout
    commits = []
    for chunk in filter(None, out.split(GR_SEP)):
        parts = chunk.strip("\n").split(GA_SEP)
        if len(parts) != 4:
            continue
        sha, date, subject, body = parts
        bullets = [ln.strip("- ").strip() for ln in body.strip().splitlines() if ln.strip().startswith("-")]
        commits.append({"sha": sha[:7], "date": date, "subject": subject.strip(), "bullets": bullets})
    return commits


def group_by_date(commits):
    grouped = {}
    for c in commits:
        grouped.setdefault(c["date"], []).append(c)
    return sorted(grouped.items(), key=lambda kv: datetime.strptime(kv[0], "%d.%m.%Y"), reverse=True)


def render_entry(c):
    bullets_html = ""
    if c["bullets"]:
        items = "\n".join(f"          <li>{html.escape(b)}</li>" for b in c["bullets"])
        bullets_html = f'\n        <ul class="changelog-bullets">\n{items}\n        </ul>'
    return f"""      <div class="changelog-entry">
        <h3>{html.escape(c['subject'])}</h3>
        <span class="changelog-hash">{c['sha']}</span>{bullets_html}
      </div>"""


def render_day(date, entries):
    entries_html = "\n".join(render_entry(c) for c in entries)
    return f"""    <div class="changelog-day">
      <div class="changelog-date">{date}</div>
      <div class="changelog-entries">
{entries_html}
      </div>
    </div>"""


PAGE_TEMPLATE = """<!doctype html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Changelog — {site} (intern)</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Figtree:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
  :root {{
    --navy: #0a1e3f; --brass: #c6a15b; --brass-deep: #a5823f;
    --paper: #f4f6fa; --white: #fff; --ink: #14243d; --slate: #57687f;
    --line: rgba(20,36,61,0.12);
    --font-display: "Fraunces", Georgia, serif;
    --font-body: "Figtree", -apple-system, sans-serif;
    --font-mono: "Space Mono", ui-monospace, monospace;
  }}
  body {{ margin:0; background:var(--paper); color:var(--ink); font-family:var(--font-body); }}
  header {{ background:var(--navy); color:#e9eef6; padding:2.2rem 1.5rem; text-align:center; }}
  header h1 {{ font-family:var(--font-display); margin:0 0 6px; font-weight:600; }}
  header p {{ margin:0; color:#a9b8ce; font-size:0.9rem; }}
  main {{ max-width:680px; margin:2.5rem auto; padding:0 1.5rem 3rem; }}
  .changelog-day {{ margin-bottom: 2.2rem; }}
  .changelog-date {{ font-family: var(--font-mono); font-size: 0.78rem; letter-spacing: 0.06em; text-transform: uppercase; color: var(--brass-deep); margin-bottom: 0.8rem; }}
  .changelog-entry {{ background: var(--white); border: 1px solid var(--line); border-radius: 10px; padding: 18px 22px; margin-bottom: 12px; box-shadow: 0 1px 2px rgba(10,30,63,0.05), 0 6px 16px -8px rgba(10,30,63,0.14); }}
  .changelog-entry h3 {{ margin: 0 0 6px; font-family: var(--font-display); font-size: 1.05rem; }}
  .changelog-hash {{ font-family: var(--font-mono); font-size: 0.76rem; color: var(--slate); }}
  .changelog-bullets {{ margin: 10px 0 0; padding-left: 1.2em; color: var(--slate); font-size: 0.92rem; }}
  .changelog-bullets li {{ margin-bottom: 4px; }}
</style>
</head>
<body>
<header>
  <h1>Changelog</h1>
  <p>{site} — automatisch aus der Git-Commit-Historie erzeugt, intern</p>
</header>
<main>
{days}
</main>
</body>
</html>
"""


def main():
    commits = get_commits()
    if not commits:
        print("Keine Commits gefunden.", file=sys.stderr)
        sys.exit(1)
    days_html = "\n".join(render_day(date, entries) for date, entries in group_by_date(commits))
    html_out = PAGE_TEMPLATE.format(site=SITE_NAME, days=days_html)
    with open("changelog.html", "w", encoding="utf-8") as f:
        f.write(html_out)
    print(f"changelog.html geschrieben ({len(commits)} Commits).")


if __name__ == "__main__":
    main()
