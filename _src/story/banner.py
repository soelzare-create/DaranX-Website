"""The sales banner shown first on every DaranX page: sale, installation and setup,
then the five equipment groups, each linking to its landing page.

The banner carries its own icon sprite (ids prefixed b-) and its own stylesheet
(assets/banner.css), so the same markup works on the story pages and on the
hand-written homepage, whose styles are separate.
"""
import re
from pathlib import Path

ICON_DIR = Path(__file__).with_name('icons')

SERVICES = [('shopping-cart', 'فروش', 'تجهیزات متناسب با نیاز واقعی'),
            ('wrench', 'نصب', 'اجرای منظم و استاندارد'),
            ('rocket-launch', 'راه‌اندازی', 'پیکربندی و تحویل آمادهٔ کار')]

# (file, card name, icon, landing-page name)
CATS = [('sale-network.html', 'شبکه', 'share-network', 'شبکه'),
        ('sale-server.html', 'سرور', 'hard-drives', 'سرور'),
        ('sale-switch-router.html', 'سوئیچ و روتر', 'network', 'سوئیچ و روتر'),
        ('sale-computer-laptop.html', 'کامپیوتر و لپ‌تاپ', 'laptop', 'کامپیوتر و لپ‌تاپ'),
        ('sale-ups.html', 'UPS و برق اضطراری', 'battery-charging', 'UPS و برق اضطراری')]


def _sprite():
    names = sorted({ic for ic, _, _ in SERVICES} | {c[2] for c in CATS} | {'arrow-left'})
    out = []
    for n in names:
        body = re.sub(r'^<svg[^>]*>|</svg>\s*$', '', (ICON_DIR / f'{n}.svg').read_text().strip())
        out.append(f'<symbol id="b-{n}" viewBox="0 0 256 256">{body}</symbol>')
    return ('<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs>'
            + ''.join(out) + '</defs></svg>')


def _ic(n, cls=''):
    c = f' class="{cls}"' if cls else ''
    return f'<svg{c} aria-hidden="true" focusable="false"><use href="#b-{n}"/></svg>'


def banner(current=None):
    svc = ''.join(f'<li><span class="dxb-n">{"۱۲۳"[k]}</span>{_ic(ic)}<strong>{name}</strong><span class="dxb-d">{d}</span></li>'
                  for k, (ic, name, d) in enumerate(SERVICES))
    cur = ' aria-current="page"'
    cats = ''.join(f'<a href="{f}"{cur if f == current else ""}>{_ic(ic)}<span class="dxb-c">{name}</span>'
                   f'<span class="dxb-go">مشاهده{_ic("arrow-left")}</span></a>' for f, name, ic, _ in CATS)
    return f'''<!-- dx-banner -->
<section class="dxb" aria-label="فروش، نصب و راه‌اندازی تجهیزات">
  {_sprite()}
  <img class="dxb-bg" src="assets/photos/home-m.jpg" alt="" width="990" height="901" decoding="async" fetchpriority="high">
  <div class="dxb-in">
    <p class="dxb-k">تجهیزات فناوری سازمان شما، با DaranX</p>
    <ul class="dxb-svc" aria-label="خدمات">{svc}</ul>
    <p class="dxb-pick">برای کدام تجهیزات؟</p>
    <nav class="dxb-cats" aria-label="فروش، نصب و راه‌اندازی بر اساس تجهیزات">{cats}</nav>
  </div>
</section>
<!-- /dx-banner -->'''


def inject_index(root, css_href):
    """put the banner (and its stylesheet) at the top of the hand-written homepage"""
    p = root / 'index.html'
    s = p.read_text(encoding='utf-8')
    link = f'<!-- dx-banner-css --><link rel="stylesheet" href="{css_href}"><!-- /dx-banner-css -->'
    if '<!-- dx-banner-css -->' in s:
        s = re.sub(r'<!-- dx-banner-css -->.*?<!-- /dx-banner-css -->', lambda m: link, s, flags=re.S)
    else:
        s = s.replace('</head>', link + '\n</head>', 1)
    if '<!-- dx-banner -->' in s:
        s = re.sub(r'<!-- dx-banner -->.*?<!-- /dx-banner -->', lambda m: banner(), s, flags=re.S)
    else:
        anchor = '<main id="top" tabindex="-1">'
        assert s.count(anchor) == 1
        s = s.replace(anchor, anchor + '\n' + banner(), 1)
    p.write_text(s, encoding='utf-8')
