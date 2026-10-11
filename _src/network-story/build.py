#!/usr/bin/env python3
"""Build network-story.html (the cinematic network page) into the site root.

    python3 _src/network-story/build.py

Inputs here: page.html (structure + words), story.css (brand + world styles),
scenes.js (camera + motion), world.py (the building, drawn by code), engine/
(cinematic engine + GSAP), icons/ (Phosphor). Output: one self-contained HTML
that uses the site's own assets/ folder (font, logo).
"""
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
import world  # noqa: E402

ROOT = HERE.parent.parent
OUT = ROOT / 'network-story.html'

# scene states: (act, selector, scenes where it applies, also in static mode?, declarations)
STATES = [
    ('a1', '#dk-f3-1 .mon', [1, 2], True, 'fill:var(--brand);stroke:#fff'),
    ('a1', '.click-ring', [1], False, 'animation:ring 1.6s var(--ease-out) infinite'),
    ('a1', '.svc', [1], True, 'opacity:1'),
    ('a1', '.pk', [1, 2], False, 'opacity:1'),
    ('a1', ':is(.flow,.riser,.uplink)', [2], True, 'stroke:var(--brand);stroke-dasharray:4 14;animation:flow 1.1s linear infinite'),
    ('a1', '.svc2', [2], True, 'opacity:1'),

    ('a2', '.node-ring', [0], False, 'stroke:#E2A35A;animation:ring 1.4s ease-out infinite'),
    ('a2', '.q', [0], True, 'opacity:1'),
    ('a2', '.node rect', [1, 2], True, 'fill:#3D1E1B;stroke:var(--alert)'),
    ('a2', '.room', [1, 2], True, 'fill:rgba(242,112,94,.10)'),
    ('a2', '.tg', [1, 2], True, 'stroke:rgba(242,112,94,.5)'),
    ('a2', '.warn', [1, 2], True, 'opacity:1'),
    ('a2', '.hunt', [2], False, 'opacity:1'),
    ('a2', '.unk', [2], True, 'opacity:1'),

    ('a3', '.L-cab', [0], False, 'opacity:.12'),
    ('a3', ':is(.ask,.hot,.hot-t)', [0], True, 'opacity:1'),
    ('a3', '.lane-t', [1, 2, 3, 4], True, 'opacity:1'),
    ('a3', '.cab.alt', [2, 3, 4], True, 'opacity:1'),
    ('a3', '.wifi', [2], True, 'opacity:1'),
    ('a3', '.tagc', [3, 4], True, 'opacity:1'),
    ('a3', ':is(.shield-box,.shield,.mon-p)', [4], True, 'opacity:1'),
    ('a3', ':is(.lane,.riser,.uplink,.alt)', [4], False, 'animation:flow 1.4s linear infinite'),

    ('a4', '.cab.alt', [0, 1, 2], True, 'opacity:1'),
    ('a4', '.shield-box', [0, 1, 2], True, 'opacity:.45'),
    ('a4', ':is(.brk,.ok-b)', [0], True, 'opacity:1'),
    ('a4', '#riser1', [0], True, 'stroke:var(--alert);opacity:.6;stroke-dasharray:6 10'),
    ('a4', '.pk', [0], False, 'opacity:1'),
    ('a4', '.newf', [1, 2], True, 'opacity:1;transform:none'),
    ('a4', ':is(.lane,.riser,.uplink,.alt)', [1, 2], False, 'animation:flow 1.4s linear infinite'),
]
# cause -> effect: the red spreads from the broken node outward, floor by floor
EXTRA = """
#a2 .room-f2{transition-delay:0ms}#a2 .room-f3{transition-delay:220ms}#a2 .room-f1{transition-delay:380ms}#a2 .room-b{transition-delay:540ms}
#a2 .warn-f2{transition-delay:120ms}#a2 .warn-f3{transition-delay:340ms}#a2 .warn-f1{transition-delay:500ms}
#a2 .unk{transition-delay:calc(var(--k) * 140ms)}
body.no-story .world,html:not(.js) .world{height:auto;aspect-ratio:1600/900}
"""


def state_css():
    out = []
    for act, sel, scenes, static, decl in STATES:
        sels = [f'#{act}[data-sc="{n}"] {sel}' for n in scenes]
        if static:
            sels += [f'body.no-story #{act} {sel}', f'html:not(.js) #{act} {sel}']
        out.append(','.join(sels) + '{' + decl + '}')
    return '\n'.join(out) + EXTRA


def main():
    page = (HERE / 'page.html').read_text(encoding='utf-8')
    eng = HERE / 'engine'
    parts = {
        'ICONS': f'<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs>{world.icon_symbols()}</defs></svg>',
        'BASE_CSS': (eng / 'base.css').read_text(encoding='utf-8'),
        'STORY_CSS': (HERE / 'story.css').read_text(encoding='utf-8'),
        'STATE_CSS': state_css(),
        'GSAP': (eng / 'gsap.min.js').read_text(encoding='utf-8'),
        'SCROLLTRIGGER': (eng / 'ScrollTrigger.min.js').read_text(encoding='utf-8'),
        'ENGINE': (eng / 'story-engine.js').read_text(encoding='utf-8'),
        'UI': (eng / 'ui.js').read_text(encoding='utf-8'),
        'SCENES': (HERE / 'scenes.js').read_text(encoding='utf-8'),
    }
    for act in ('a1', 'a2', 'a3', 'a4'):
        parts['WORLD_' + act] = world.world(act)
    for k, v in parts.items():
        token = '{{' + k + '}}'
        assert page.count(token) == 1, token
        page = page.replace(token, v)
    assert '{{' not in page, page[page.index('{{'):page.index('{{') + 40]
    OUT.write_text(page, encoding='utf-8')
    print(f'wrote {OUT.relative_to(ROOT)} ({len(page.encode()) // 1024} KB)')


if __name__ == '__main__':
    main()
