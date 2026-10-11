"""Shared builder for the five sales landing pages (sale, installation and setup per equipment group).

Every landing page is a story in the same building world, told the same way:
1 the need (hero + two scenes)
2 blueprint: فروش (the right equipment is chosen), نصب (it is put in place), راه‌اندازی (it is set up and tested)
3 ready to work, with support after delivery; then the plain sections and the call to action.

A page gives its own layers (strings drawn with the kit) and captions; this module wraps them
into acts, scene states and the PAGE dict. Layers are grouped by class:
  n1, n2      the need, shown in act 1 scenes 1 and 2
  g-sale      the chosen equipment (chips over the roof)
  g-inst      the equipment in place
  g-setup     configured and tested
  g-ready     delivered, with support
"""
from kit import *
import layout as S

WIDE = {'z': 1, 'x': 540, 'y': 470}
HIGH = {'z': .95, 'x': 540, 'y': 430}                  # a little higher: the chosen equipment sits over the roof
FLOW = ':is(.flow,.riser,.uplink,.lane)', 'stroke:var(--brand);stroke-dasharray:4 14;animation:flow 1.1s linear infinite'


def cart(items):
    """the equipment chosen for this job: a row of chips over the roof"""
    w = [text_w(t) + 78 for t, _ in items]
    gap = 14
    x = 600 + (sum(w) + gap * (len(w) - 1)) / 2           # right edge: the row reads right to left
    out = []
    for (t, ic), wi in zip(items, w):
        out.append(chip(x - wi / 2, 104, t, 'chip', ic))
        x -= wi + gap
    return ''.join(out)


def support():
    return group('g-ready', chip(600, 104, 'تحویل با مستندات و پشتیبانی پس از آن', 'chip ok-c', 'headset'))


def svc_cards(name, sale, install, setup):
    cards = [('فروش', 'shopping-cart', sale), ('نصب', 'wrench', install), ('راه‌اندازی', 'rocket-launch', setup)]
    return ''.join(f'''<article class="lp-card"><svg class="lp-ic" aria-hidden="true" focusable="false"><use href="#b-{ic}"/></svg>
  <h3>{t} {name}</h3><ul>{''.join(f'<li>{x}</li>' for x in items)}</ul></article>''' for t, ic, items in cards)


def make(file, name, h1, lead, title, description, keywords, sale, install, setup, gear, summary, faq, design_link, st):
    """name: the equipment group as it reads after «فروش» (e.g. «سرور»);
    design_link: (label, href) of the DaranX design page for this group, or None;
    st: the story: base (list of layers), n1, n2, cart (list of (text, icon)), inst, setup, asks,
        caps {n1, n2, lead, sale, inst, setup, ready: (title, line)}, states (extra), css (extra)"""
    C = st['caps']
    base = st['base']
    sale_l = group('g-sale', cart(st['cart']))
    inst_l = group('g-inst', st['inst'])
    setup_l = group('g-setup', st['setup'])
    a1 = world('a1', base + [group('n1', st['n1']), group('n2', st['n2'])])
    a2 = world('a2', base + [asks(st['asks'], x=st.get('ask_x', 720)), sale_l, inst_l, setup_l])
    a3 = world('a3', base + [inst_l, setup_l, support()])
    body = f'''<section class="sec" id="services3" aria-labelledby="h-s3"><div class="wrap">
  <span class="kicker" data-reveal>سه خدمت</span>
  <h2 id="h-s3" data-reveal>از انتخاب تا تحویل آمادهٔ کار</h2>
  <div class="lp-cards" data-reveal>{svc_cards(name, sale, install, setup)}</div>
</div></section>
<section class="sec" id="gear" aria-labelledby="h-gear"><div class="wrap">
  <span class="kicker" data-reveal>تجهیزات</span>
  <h2 id="h-gear" data-reveal>چه تجهیزاتی تأمین می‌کنیم؟</h2>
  <ul class="lp-gear" data-reveal>{''.join(f'<li>{g}</li>' for g in gear)}</ul>
  {f'<p class="lp-note" data-reveal>به طراحی هم نیاز دارید؟ <a href="{design_link[1]}">{design_link[0]}</a></p>' if design_link else ''}
</div></section>
'''
    links = [(c[1], c[0]) for c in S.banner.CATS if c[0] != file]
    if design_link:
        links.append(design_link)
    states = [
        ('a1', '.n1', [1, 2], True, 'opacity:1'),
        ('a1', ':is(.n2,.n2 .brk)', [2], True, 'opacity:1'),
        ('a2', '.ask', [0], True, 'opacity:1'),
        ('a2', '.g-sale', [1, 2, 3], True, 'opacity:1'),
        ('a2', '.g-inst', [2, 3], True, 'opacity:1'),
        ('a2', '.g-setup', [3], True, 'opacity:1'),
        ('a2', FLOW[0], [3], False, FLOW[1]),
        ('a3', ':is(.g-inst,.g-setup,.g-ready)', [0, 1], True, 'opacity:1'),
        ('a3', FLOW[0], [0, 1], False, FLOW[1]),
    ] + st.get('states', [])
    return {
        'file': file, 'hub': None,
        'title': title, 'description': description, 'keywords': keywords,
        'crumbs': [('فروش، نصب و راه‌اندازی ' + name, file)],
        'badge': 'فروش · نصب · راه‌اندازی',
        'h1': h1, 'verdict': lead, 'cta2': ('سه مرحله را ببینید', '#a2'),
        'service': {'name': f'فروش، نصب و راه‌اندازی {name}', 'type': f'Sale, installation and setup: {name}', 'desc': description},
        'acts': [
            {'id': 'a1', 'hero': True, 'len': 400, 'label': f'نیاز سازمان به {name}', 'world': a1,
             'caps': [(1, 'p', *C['n1']), (2, 'p', *C['n2'])],
             'timeline': {'bounds': [.22, .6], 'stops': [0, .42, 1], 'cam': [WIDE]}},
            {'id': 'a2', 'blueprint': True, 'len': 520, 'label': f'فروش، نصب و راه‌اندازی {name}', 'world': a2,
             'caps': [(0, 'lead', *C['lead']), (1, 'h3', 'فروش', C['sale']), (2, 'h3', 'نصب', C['inst']), (3, 'h3', 'راه‌اندازی', C['setup'])],
             'timeline': {'bounds': [.24, .5, .74], 'stops': [.1, .37, .62, .9], 'cam': [HIGH]}},
            {'id': 'a3', 'blueprint': True, 'len': 320, 'label': f'{name}، آمادهٔ کار', 'world': a3,
             'caps': [(0, 'lead', *C['ready']),
                      (1, 'h3', 'همه چیز سر جای درستش.', 'نیازتان را بگویید؛ پیشنهاد مناسب و قیمت را آماده می‌کنیم.', S.final_actions())],
             'timeline': {'bounds': [.6], 'stops': [.3, 1], 'cam': [HIGH]}},
        ],
        'states': states,
        'css': """.n1,.n2,.g-sale,.g-inst,.g-setup,.g-ready{opacity:0;transition:opacity .6s var(--ease-out)}
.ok-c rect{stroke:#7FD1A8}.ok-c .ic{fill:#7FD1A8}
""" + st.get('css', ''),
        'before_sections': body,
        'summary': summary,
        'why': ['یک مسئول از خرید تا راه‌اندازی', 'تجهیزات متناسب با نیاز واقعی', 'اجرای منظم و مستند', 'پشتیبانی پس از تحویل'],
        'why_h2': 'چرا از DaranX؟',
        'method_h2': 'از نیاز تا تحویل، در چهار مرحله',
        'method': [('شناخت', 'نیاز واقعی، وضعیت فعلی و برنامهٔ رشد را می‌شناسیم.'), ('انتخاب', 'تجهیزات مناسب همان نیاز را پیشنهاد و تأمین می‌کنیم.'),
                   ('نصب و راه‌اندازی', 'نصب منظم، پیکربندی، تست و تحویل آمادهٔ کار.'), ('پشتیبانی', 'نگهداری و پاسخ‌گویی پس از تحویل.')],
        'faq': faq,
        'related': {'h2': 'تجهیزات دیگر', 'links': links},
        'cta': {'h2': f'فروش، نصب و راه‌اندازی {name} را به ما بسپارید.', 'p': 'نیازتان را بگویید؛ پیشنهاد مناسب و قیمت را برایتان آماده می‌کنیم.'},
    }
