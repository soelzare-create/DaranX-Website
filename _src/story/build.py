#!/usr/bin/env python3
"""Build every DaranX story page and the shared engine files.

    python3 _src/story/build.py            # all pages
    python3 _src/story/build.py network    # one page

Writes assets/story/story.css and assets/story/story.js (shared by all pages and
cached by the browser; the ?v= hash changes when their content changes) and one
HTML file per module in pages/ into the site root.
"""
import hashlib
import importlib
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
sys.path.insert(0, str(HERE / 'pages'))
import layout  # noqa: E402

ROOT = HERE.parent.parent
ENG = HERE / 'engine'
OUT_ASSETS = ROOT / 'assets' / 'story'


def shared():
    css = (ENG / 'base.css').read_text(encoding='utf-8') + '\n' + (HERE / 'story.css').read_text(encoding='utf-8')
    js = '\n;\n'.join((ENG / n).read_text(encoding='utf-8') for n in ('gsap.min.js', 'ScrollTrigger.min.js', 'story-engine.js')) \
        + '\n;\n' + (HERE / 'runner.js').read_text(encoding='utf-8') + '\n;\n' + (ENG / 'ui.js').read_text(encoding='utf-8')
    OUT_ASSETS.mkdir(parents=True, exist_ok=True)
    (OUT_ASSETS / 'story.css').write_text(css, encoding='utf-8')
    (OUT_ASSETS / 'story.js').write_text(js, encoding='utf-8')
    (OUT_ASSETS / 'LICENSE-GSAP.txt').write_text((ENG / 'LICENSE-GSAP.txt').read_text(encoding='utf-8'), encoding='utf-8')
    v = lambda s: hashlib.sha1(s.encode()).hexdigest()[:10]
    return f'assets/story/story.css?v={v(css)}', f'assets/story/story.js?v={v(js)}', len(css), len(js)


def main(only):
    css_href, js_href, nc, nj = shared()
    print(f'shared: story.css {nc // 1024} KB, story.js {nj // 1024} KB')
    names = only or sorted(p.stem for p in (HERE / 'pages').glob('*.py') if not p.stem.startswith('_'))
    for name in names:
        layout.kit.USED_ICONS.clear()
        P = importlib.import_module(name).build()
        html = layout.page(P, css_href, js_href)
        assert '{{' not in html
        (ROOT / P['file']).write_text(html, encoding='utf-8')
        print(f'  {P["file"]:24} {len(html.encode()) // 1024:4} KB')


if __name__ == '__main__':
    main(sys.argv[1:])
