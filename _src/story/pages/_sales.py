"""Shared builder for the five sales landing pages (sale, installation and setup per equipment group).

Each landing page opens with the site banner (its own card marked current), then a short hero
with the page's single h1, the three services spelled out for this equipment, the equipment
we supply, the method, FAQ and a call to action. No story acts: these pages exist to convert.
"""
import layout as S


def svc_cards(name, sale, install, setup):
    cards = [('فروش', 'shopping-cart', sale), ('نصب', 'wrench', install), ('راه‌اندازی', 'rocket-launch', setup)]
    return ''.join(f'''<article class="lp-card"><svg class="lp-ic" aria-hidden="true" focusable="false"><use href="#b-{ic}"/></svg>
  <h3>{t} {name}</h3><ul>{''.join(f'<li>{x}</li>' for x in items)}</ul></article>''' for t, ic, items in cards)


def make(file, name, h1, lead, title, description, keywords, sale, install, setup, gear, summary, faq, design_link, why=None):
    """name: the equipment group as it reads after «فروش» (e.g. «سرور»);
    design_link: (label, href) of the DaranX design page for this group, or None"""
    hero = f'''<section class="lp-hero" aria-labelledby="lp-h1"><div class="wrap">
  <p class="lp-crumbs"><a href="index.html">DaranX</a> › فروش، نصب و راه‌اندازی {name}</p>
  <span class="lp-badge">فروش · نصب · راه‌اندازی</span>
  <h1 id="lp-h1">{h1}</h1>
  <p class="lp-lead">{lead}</p>
  <div class="hero-actions"><a class="btn btn-brand" href="index.html#contact">درخواست مشاوره و قیمت</a><a class="btn btn-ghost" href="tel:+982188964116">تماس: {S.TEL}</a></div>
</div></section>'''
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
    return {
        'file': file, 'hub': None,
        'title': title, 'description': description, 'keywords': keywords,
        'crumbs': [('فروش، نصب و راه‌اندازی ' + name, file)],
        'h1': h1,
        'service': {'name': f'فروش، نصب و راه‌اندازی {name}', 'type': f'Sale, installation and setup: {name}',
                    'desc': description},
        'acts': [], 'states': [],
        'hero_html': hero, 'before_sections': body,
        'css': '',
        'summary': summary,
        'why': why or ['یک مسئول از خرید تا راه‌اندازی', 'تجهیزات متناسب با نیاز واقعی', 'اجرای منظم و مستند', 'پشتیبانی پس از تحویل'],
        'why_h2': 'چرا از DaranX؟',
        'method_h2': 'از نیاز تا تحویل، در چهار مرحله',
        'method': [('شناخت', 'نیاز واقعی، وضعیت فعلی و برنامهٔ رشد را می‌شناسیم.'), ('انتخاب', 'تجهیزات مناسب همان نیاز را پیشنهاد و تأمین می‌کنیم.'),
                   ('نصب و راه‌اندازی', 'نصب منظم، پیکربندی، تست و تحویل آمادهٔ کار.'), ('پشتیبانی', 'نگهداری و پاسخ‌گویی پس از تحویل.')],
        'faq': faq,
        'related': {'h2': 'تجهیزات دیگر', 'links': links},
        'cta': {'h2': f'فروش، نصب و راه‌اندازی {name} را به ما بسپارید.', 'p': 'نیازتان را بگویید؛ پیشنهاد مناسب و قیمت را برایتان آماده می‌کنیم.'},
    }
