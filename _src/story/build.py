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
import banner  # noqa: E402

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
    bcss = (HERE / 'banner.css').read_text(encoding='utf-8')
    (ROOT / 'assets' / 'banner.css').write_text(bcss, encoding='utf-8')
    v = lambda s: hashlib.sha1(s.encode()).hexdigest()[:10]
    return (f'assets/story/story.css?v={v(css)}', f'assets/story/story.js?v={v(js)}', f'assets/banner.css?v={v(bcss)}'), len(css), len(js)


ORDER = ['solutions', 'infrastructure', 'network', 'datacenter-server', 'storage', 'power-ups', 'passive',
         'security', 'network-security', 'cctv', 'access-monitoring', 'access-control',
         'intelligence', 'ai-solutions', 'data-analytics', 'automation', 'software-models',
         'sale-network', 'sale-server', 'sale-switch-router', 'sale-computer-laptop', 'sale-ups']


def llms(built):
    """llms.txt (an index for AI answer engines) and llms-full.txt (every page's plain summary and FAQ)"""
    site, plain = layout.SITE, layout.plain
    head = ['# DaranX', '',
            '> DaranX (داران ایکس) شریک فناوری سازمان‌ها و کسب‌وکارها در تهران است و زیرساخت، امنیت و هوشمندی را '
            'از شناخت و طراحی تا اجرا و پشتیبانی، به‌عنوان یک راهکار یکپارچه طراحی می‌کند. عضو هلدینگ بهسرما.', '',
            f'- Website: {site}', '- Language: Persian (fa-IR)', '- Phone: +98 21 8896 4116, +98 935 937 0910',
            '- Address: تهران، میدان فاطمی، نبش چهلستون، ساختمان چهلستون، طبقه ۲، واحد ۲۰۲',
            '- Method: شناخت، طراحی، اجرا، پشتیبانی', '- Parent organisation: Behsarma Holding (https://behsarmaholding.com/)', '']
    idx, full = list(head), list(head)
    groups = {'solutions': 'راهکارها', 'infrastructure': 'زیرساخت', 'security': 'امنیت', 'intelligence': 'هوشمندی',
              'sale-network': 'فروش، نصب و راه‌اندازی تجهیزات'}
    for name in ORDER:
        P = built[name]
        if name in groups:
            idx += ['', f'## {groups[name]}', '']
        idx.append(f'- [{plain(P["h1"])}]({site}{P["file"]}): {P["description"]}')
        full += ['', f'## {plain(P["h1"])}', f'URL: {site}{P["file"]}', '', *[plain(t) for t in P['summary']['paras']], '',
                 *[f'- {plain(t)}' for t in P['summary']['bullets']], '', '### پرسش‌های رایج', '']
        full += [f'**{plain(q)}** {plain(a)}' for q, a in P['faq']]
    idx += ['', '## Optional', '', f'- [Full text of every page]({site}llms-full.txt)', f'- [Sitemap]({site}sitemap.xml)']
    (ROOT / 'llms.txt').write_text('\n'.join(idx) + '\n', encoding='utf-8')
    (ROOT / 'llms-full.txt').write_text('\n'.join(full) + '\n', encoding='utf-8')
    print('  llms.txt, llms-full.txt')


def main(only):
    (css_href, js_href, banner_href), nc, nj = shared()
    banner.inject_index(ROOT, banner_href)
    print(f'shared: story.css {nc // 1024} KB, story.js {nj // 1024} KB')
    names = only or sorted(p.stem for p in (HERE / 'pages').glob('*.py') if not p.stem.startswith('_'))
    built = {}
    for name in names:
        layout.kit.USED_ICONS.clear()
        P = built[name] = importlib.import_module(name).build()
        html = layout.page(P, css_href, js_href, banner_href)
        assert '{{' not in html
        (ROOT / P['file']).write_text(html, encoding='utf-8')
        print(f'  {P["file"]:24} {len(html.encode()) // 1024:4} KB')
    if not only:
        llms(built)


if __name__ == '__main__':
    main(sys.argv[1:])
