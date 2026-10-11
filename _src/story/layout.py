"""Page assembly for the DaranX story pages: head (SEO + GEO), top bar, acts,
plain-text sections, footer, fixed contact bar and the per-page timeline.

A page module (pages/*.py) returns a PAGE dict; build.py turns it into HTML.
"""
import html
import json

import kit
import banner

SITE = 'https://www.daranx.com/'
ORG = {
    '@type': 'Organization', '@id': SITE + '#org', 'name': 'DaranX', 'alternateName': ['داران ایکس', 'Daran X'],
    'url': SITE, 'logo': SITE + 'assets/daranx-logo.svg', 'image': SITE + 'assets/og.jpg',
    'slogan': 'همه چیز سر جای درستش.',
    'description': 'DaranX شریک فناوری سازمان‌ها و کسب‌وکارهاست: زیرساخت، امنیت و هوشمندی را از شناخت و طراحی تا اجرا و پشتیبانی، به‌عنوان یک راهکار یکپارچه طراحی می‌کند.',
    'telephone': '+982188964116',
    'contactPoint': [{'@type': 'ContactPoint', 'telephone': '+982188964116', 'contactType': 'customer service', 'areaServed': 'IR', 'availableLanguage': ['fa']},
                     {'@type': 'ContactPoint', 'telephone': '+989359370910', 'contactType': 'sales', 'areaServed': 'IR', 'availableLanguage': ['fa']}],
    'address': {'@type': 'PostalAddress', 'streetAddress': 'میدان فاطمی، نبش چهلستون، ساختمان چهلستون، طبقه ۲، واحد ۲۰۲',
                'addressLocality': 'تهران', 'addressCountry': 'IR'},
    'parentOrganization': {'@type': 'Organization', 'name': 'هلدینگ بهسرما', 'alternateName': 'Behsarma Holding', 'url': 'https://behsarmaholding.com/'},
}
WEBSITE = {'@type': 'WebSite', '@id': SITE + '#website', 'url': SITE, 'name': 'DaranX', 'inLanguage': 'fa-IR', 'publisher': {'@id': SITE + '#org'}}
NAV = [('همه راهکارها', 'solutions.html', 'solutions'), ('زیرساخت', 'infrastructure.html', 'infrastructure'),
       ('امنیت', 'security.html', 'security'), ('هوشمندی', 'intelligence.html', 'intelligence'), ('تماس', 'index.html#contact', 'contact')]
TEL = '<bdi dir="ltr">۰۲۱-۸۸۹۶۴۱۱۶</bdi>'
MODIFIED = '2026-10-11'


def esc(s):
    return html.escape(s, quote=True)


def plain(s):
    """strip tags for meta/JSON-LD text"""
    import re
    return re.sub(r'<[^>]+>', '', re.sub(r'<br\s*/?>', ' ', s))


# ---------------------------------------------------------------- head
def head(P, css_href):
    url = SITE + ('' if P['file'] == 'index.html' else P['file'])
    robots = P.get('robots', 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1')
    og_desc = P.get('og_description', P['description'])
    kw = P.get('keywords')
    return f'''<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>{esc(P['title'])}</title>
<meta name="description" content="{esc(P['description'])}">
{f'<meta name="keywords" content="{esc(kw)}">' if kw else ''}
<meta name="robots" content="{robots}">
<link rel="canonical" href="{url}">
<link rel="alternate" hreflang="fa-IR" href="{url}"><link rel="alternate" hreflang="x-default" href="{url}">
<meta name="theme-color" content="#081627">
<meta name="color-scheme" content="light dark">
<meta name="author" content="DaranX">
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
<meta property="og:type" content="website"><meta property="og:site_name" content="DaranX"><meta property="og:locale" content="fa_IR">
<meta property="og:title" content="{esc(P['title'])}">
<meta property="og:description" content="{esc(og_desc)}">
<meta property="og:url" content="{url}"><meta property="og:image" content="{SITE}assets/og.jpg">
<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630"><meta property="og:image:alt" content="DaranX، همه چیز سر جای درستش.">
<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="{esc(P['title'])}"><meta name="twitter:description" content="{esc(og_desc)}"><meta name="twitter:image" content="{SITE}assets/og.jpg">
<link rel="preload" href="assets/fonts/Vazirmatn-VF.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{css_href}">
<script>document.documentElement.classList.add('js');</script>
<script type="application/ld+json">
{json.dumps(jsonld(P, url), ensure_ascii=False, separators=(',', ':'))}
</script>
'''


def jsonld(P, url):
    crumbs = P['crumbs']
    graph = [ORG, WEBSITE]
    bc = {'@type': 'BreadcrumbList', '@id': url + '#breadcrumb', 'itemListElement': [
        {'@type': 'ListItem', 'position': 1, 'name': 'DaranX', 'item': SITE}] + [
        {'@type': 'ListItem', 'position': i + 2, 'name': n, 'item': SITE + u} for i, (n, u) in enumerate(crumbs)]}
    page = {'@type': P.get('page_type', 'WebPage'), '@id': url + '#webpage', 'url': url, 'name': P['title'],
            'description': P['description'], 'inLanguage': 'fa-IR', 'isPartOf': {'@id': SITE + '#website'},
            'breadcrumb': {'@id': url + '#breadcrumb'}, 'primaryImageOfPage': SITE + 'assets/og.jpg', 'dateModified': MODIFIED,
            'speakable': {'@type': 'SpeakableSpecification', 'cssSelector': ['#what h2', '#what .prose p']}}
    graph += [page, bc]
    svc = P.get('service')
    if svc:
        s = {'@type': 'Service', '@id': url + '#service', 'name': svc['name'], 'serviceType': svc['type'],
             'description': svc['desc'], 'provider': {'@id': SITE + '#org'}, 'areaServed': {'@type': 'Country', 'name': 'Iran'},
             'url': url, 'availableLanguage': 'fa'}
        if svc.get('parts'):
            s['hasOfferCatalog'] = {'@type': 'OfferCatalog', 'name': svc['name'], 'itemListElement': [
                {'@type': 'Offer', 'itemOffered': {'@type': 'Service', 'name': n, 'url': SITE + u}} for n, u in svc['parts']]}
        page['about'] = {'@id': url + '#service'}
        page['mainEntity'] = {'@id': url + '#service'}
        graph.append(s)
    if P.get('faq'):
        graph.append({'@type': 'FAQPage', '@id': url + '#faq', 'mainEntity': [
            {'@type': 'Question', 'name': plain(q), 'acceptedAnswer': {'@type': 'Answer', 'text': plain(a)}} for q, a in P['faq']]})
    return {'@context': 'https://schema.org', '@graph': graph}


# ---------------------------------------------------------------- top bar
def topbar(P):
    cur = P.get('hub')
    links = []
    for name, href, key in NAV:
        attrs = ''
        if href == P['file']:
            attrs = ' aria-current="page" class="cur"'
        elif key == cur:
            attrs = ' class="cur"'
        links.append(f'<a href="{href}"{attrs}>{name}</a>')
    return f'''<header class="topbar" id="topbar"><div class="wrap">
  <a href="index.html" class="wm" aria-label="DaranX، صفحهٔ اصلی"><span class="logomark" aria-hidden="true"></span><b>Daran<span>X</span></b></a>
  <nav class="nav" id="nav" aria-label="ناوبری اصلی">{''.join(links)}</nav>
  <div class="tb-end"><a class="btn btn-brand btn-sm" href="index.html#contact">درخواست مشاوره</a>
  <button class="menu-btn" id="menuBtn" type="button" aria-expanded="false" aria-controls="nav" aria-label="منو"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button></div>
</div><div class="progress" aria-hidden="true"><i id="prog"></i></div></header>'''


# ---------------------------------------------------------------- acts
def cap(c):
    s, kind, title, line = c[:4]
    extra = c[4] if len(c) > 4 else ''
    cls = 'cap lead' if kind == 'lead' else 'cap'
    tag = {'lead': 'h2', 'h3': 'h3', 'p': 'p'}[kind]
    tattr = ' class="cap-t"' if kind == 'p' else ''
    return f'<div class="{cls}" data-s="{s}"><{tag}{tattr}>{title}</{tag}><p>{line}</p>{extra}</div>'


def act_html(P, a):
    hero = ''
    if a.get('hero'):
        crumbs = ' › '.join(f'<a href="{u}">{n}</a>' if i < len(P['crumbs']) - 1 else n for i, (n, u) in enumerate(P['crumbs']))
        c2 = P.get('cta2')
        hero = f'''<div class="story-copy" id="storyCopy">
      {f'<p class="crumbs">{crumbs}</p>' if len(P['crumbs']) > 1 else ''}
      <span class="badge">{P['badge']}</span>
      <h1>{P['h1']}</h1>
      <p class="verdict">{P['verdict']}</p>
      <div class="hero-actions"><a class="btn btn-brand" href="index.html#contact">درخواست مشاوره</a>{f'<a class="btn btn-ghost" href="{c2[1]}">{c2[0]}</a>' if c2 else ''}</div>
    </div>'''
    caps = ''.join(cap(c) for c in a['caps'])
    return f'''<section class="story{' bp' if a.get('blueprint') else ''}" id="{a['id']}"{' data-hero' if a.get('hero') else ''} style="--len:{a['len']}svh" aria-label="{esc(a['label'])}">
  <div class="stage">
    {a['world']}
    <div class="vignette" aria-hidden="true"></div>
    {hero}
    <div class="caps">{caps}</div>
  </div>
</section>'''


def final_actions(href2=None, label2=None):
    second = f'<a class="btn btn-ghost" href="{href2}">{label2}</a>' if href2 else f'<a class="btn btn-ghost" href="tel:+982188964116">{TEL}</a>'
    return f'<div class="hero-actions"><a class="btn btn-brand" href="index.html#contact">درخواست مشاوره</a>{second}</div>'


# ---------------------------------------------------------------- plain sections
def sections(P):
    S = P['summary']
    out = [f'''<section class="sec" id="what" aria-labelledby="h-what"><div class="wrap">
  <span class="kicker" data-reveal>خلاصه</span>
  <h2 id="h-what" data-reveal>{S['h2']}</h2>
  <div class="prose" data-reveal>
    {''.join(f'<p>{p}</p>' for p in S['paras'])}
    {'<ul>' + ''.join(f'<li>{b}</li>' for b in S['bullets']) + '</ul>' if S.get('bullets') else ''}
  </div>
</div></section>''']
    if P.get('parts'):
        T = P['parts']
        out.append(f'''<section class="sec" id="parts" aria-labelledby="h-parts"><div class="wrap">
  <span class="kicker" data-reveal>زیرمجموعه‌ها</span>
  <h2 id="h-parts" data-reveal>{T['h2']}</h2>
  <div class="parts" data-reveal>{''.join(f'<a class="part" href="{u}"><h3>{n}</h3><p>{d}</p><span class="go">جزئیات {n}</span></a>' for n, u, d in T['items'])}</div>
</div></section>''')
    if P.get('why'):
        out.append(f'''<section class="sec" id="why" aria-labelledby="h-why"><div class="wrap">
  <span class="kicker" data-reveal>چرا مهم است؟</span>
  <h2 id="h-why" data-reveal>{P.get('why_h2', 'نتیجه برای سازمان شما')}</h2>
  <ul class="why" data-reveal>{''.join(f'<li>{w}</li>' for w in P['why'])}</ul>
</div></section>''')
    if P.get('method'):
        out.append(f'''<section class="sec" id="method" aria-labelledby="h-method"><div class="wrap">
  <span class="kicker" data-reveal>روش کار</span>
  <h2 id="h-method" data-reveal>{P.get('method_h2', 'از مسئله تا پایداری، در چهار مرحله')}</h2>
  <ol class="steps4" data-reveal>{''.join(f'<li><h3>{h}</h3><p>{p}</p></li>' for h, p in P['method'])}</ol>
</div></section>''')
    if P.get('faq'):
        out.append(f'''<section class="sec" id="faq" aria-labelledby="h-faq"><div class="wrap">
  <h2 id="h-faq" data-reveal>پرسش‌های رایج</h2>
  <div class="faq">{''.join(f'<details><summary>{q}</summary><p class="ans">{a}</p></details>' for q, a in P['faq'])}</div>
</div></section>''')
    if P.get('related'):
        R = P['related']
        out.append(f'''<section class="sec" id="more" aria-labelledby="h-more"><div class="wrap">
  <h2 id="h-more" data-reveal>{R['h2']}</h2>
  <nav class="links" aria-label="{esc(plain(R['h2']))}" data-reveal>{''.join(f'<a href="{u}">{n}</a>' for n, u in R['links'])}</nav>
</div></section>''')
    C = P['cta']
    out.append(f'''<section class="sec" id="contact-cta" aria-labelledby="h-cta"><div class="wrap">
  <div class="cta-box" data-reveal>
    <h2 id="h-cta">{C['h2']}</h2>
    <p>{C['p']}</p>
    <div class="hero-actions"><a class="btn btn-brand" href="index.html#contact">درخواست مشاوره</a><a class="btn btn-ghost" href="tel:+982188964116">تماس: {TEL}</a></div>
  </div>
</div></section>''')
    return '\n'.join(out)


FOOTER = '''<footer class="site foot"><div class="wrap">
  <div class="foot-grid">
    <div class="foot-brand">
      <div class="brand"><span class="logomark" aria-hidden="true"></span><span>Daran<span style="color:var(--steel-soft)">X</span></span></div>
      <p>سه حوزه، یک راهکار یکپارچه. زیرساخت را می‌سازیم، از آن محافظت می‌کنیم و آن را هوشمند می‌کنیم؛ مسئولیت نتیجه را به عهده می‌گیریم و پشتیبانی می‌کنیم.</p>
    </div>
    <div class="foot-col"><h2>حوزه‌ها</h2><a href="infrastructure.html">زیرساخت</a><a href="security.html">امنیت</a><a href="intelligence.html">هوشمندی</a><a href="solutions.html">همه راهکارها</a></div>
    <div class="foot-col"><h2>زیرساخت</h2><a href="network.html">شبکه</a><a href="datacenter-server.html">دیتاسنتر و سرور</a><a href="storage.html">ذخیره‌سازی</a><a href="power-ups.html">برق و UPS</a><a href="passive.html">زیرساخت پسیو</a></div>
    <div class="foot-col"><h2>امنیت و هوشمندی</h2><a href="network-security.html">امنیت شبکه</a><a href="cctv.html">دوربین مداربسته</a><a href="access-monitoring.html">دسترسی و پایش</a><a href="access-control.html">کنترل تردد</a><a href="ai-solutions.html">هوش مصنوعی</a><a href="data-analytics.html">داده و تحلیل</a><a href="automation.html">خودکارسازی</a><a href="software-models.html">نرم‌افزار و مدل اختصاصی</a></div>
    <div class="foot-col"><h2>تماس</h2>
      <div class="fc-line"><a href="tel:+982188964116">۰۲۱-۸۸۹۶۴۱۱۶</a></div><div class="fc-line"><a href="tel:+982188966904">۰۲۱-۸۸۹۶۶۹۰۴</a></div>
      <div class="fc-line"><a href="tel:+989359370910">۰۹۳۵-۹۳۷۰۹۱۰</a></div>
      <address class="fc-line">تهران، میدان فاطمی، نبش چهلستون، ساختمان چهلستون، طبقه ۲، واحد ۲۰۲</address>
    </div>
  </div>
  <div class="foot-parent">
    <span class="fp-label">هلدینگ مادر</span>
    <div class="fp-main"><a href="https://behsarmaholding.com/" title="هلدینگ بهسرما">عضو هلدینگ بهسرما</a><small>DaranX یکی از مجموعه‌های هلدینگ بهسرما است.</small></div>
    <span class="fp-en">Behsarma Holding · behsarmaholding.com</span>
  </div>
  <div class="foot-bottom"><span style="color:var(--on-navy-soft)">همه چیز سر جای درستش.</span><span>© ۱۴۰۵ DaranX، تمامی حقوق محفوظ است.</span></div>
</div></footer>'''

FBAR = '''<!-- Fixed contact bar: on every page, always on screen, never moves or animates on scroll. -->
<aside class="fbar" aria-label="تماس و هلدینگ مادر">
  <div class="fbar-in">
    <div class="fbar-call">
      <span class="fbar-label">مشاورهٔ رایگان:</span>
      <span class="fbar-phones"><a href="tel:+982188964116">۰۲۱-۸۸۹۶۴۱۱۶</a><a class="fbar-mob" href="tel:+989359370910">۰۹۳۵-۹۳۷۰۹۱۰</a></span>
      <a class="fbar-btn" href="tel:+982188964116"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>تماس بگیرید</a>
    </div>
    <span class="fbar-sep" aria-hidden="true"></span>
    <div class="fbar-hold">عضو هلدینگ <a href="https://behsarmaholding.com/" title="هلدینگ بهسرما">بهسرما</a></div>
  </div>
</aside>'''


# ---------------------------------------------------------------- state CSS + timeline
def state_css(states):
    out = []
    for act, sel, scenes, static, decl in states:
        sels = [f'#{act}[data-sc="{n}"] {sel}' for n in scenes]
        if static:
            sels += [f'body.no-story #{act} {sel}', f'html:not(.js) #{act} {sel}']
        out.append(','.join(sels) + '{' + decl + '}')
    return '\n'.join(out)


def timeline(P):
    acts = []
    for a in P['acts']:
        t = dict(a['timeline'])
        t['id'] = a['id']
        if a.get('hero'):
            t['hero'] = True
        acts.append(t)
    return json.dumps({'acts': acts}, ensure_ascii=False, separators=(',', ':'))


def page(P, css_href, js_href, banner_href):
    acts = '\n'.join(act_html(P, a) for a in P['acts'])     # icons were registered while the page module drew its worlds
    icons = ('<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs>'
             '<marker id="arrowhead" viewBox="0 0 10 10" refX="7" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">'
             '<path d="M0 0L10 5L0 10z" fill="context-stroke"/></marker>' + kit.icon_symbols() + '</defs></svg>')
    return (head(P, css_href) + f'<link rel="stylesheet" href="{banner_href}">\n'
            + f'<style>\n{state_css(P["states"])}\n{P.get("css", "")}\n</style>\n</head>\n<body>\n'
            + icons + '\n<a class="skip" href="#what">پرش به خلاصهٔ متنی صفحه</a>\n'
            + topbar(P) + '\n<div id="doc"><main id="top" tabindex="-1">\n' + banner.banner(P['file']) + '\n'
            + P.get('hero_html', '') + acts + '\n'
            + P.get('before_sections', '') + sections(P) + '\n</main>\n' + FOOTER + '\n</div>\n' + FBAR + '\n'
            + f'<script>window.STORY={timeline(P)};</script>\n'
            + f'<script src="{js_href}" defer></script>\n</body>\n</html>\n')
