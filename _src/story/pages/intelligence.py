"""هوشمندی (hub): از داده‌ای که دارید، تصمیم بهتری بسازید (problem + assemble).
1 data exists but builds no decision: repetitive work, slow decisions, scattered data
2 the four parts, one per scene, each linking to its page
3 step by step and measurable, then monitored and improved
"""
from kit import *
import layout as S

WIDE = {'z': 1, 'x': 540, 'y': 470}
CURVE = 'M820 215 C620 215 420 270 420 380 C420 480 700 450 700 545'
PARTS = [('راهکارها و دستیارهای هوش مصنوعی', 'ai-solutions.html', 'هوش مصنوعی و دستیارهای هوشمند، جایی که برای مسئلهٔ واقعی ارزش بسازند.'),
         ('داده و تحلیل', 'data-analytics.html', 'تبدیل داده‌های پراکنده به تصویری روشن و قابل تصمیم برای مدیران.'),
         ('خودکارسازی هوشمند', 'automation.html', 'حذف کارهای دستی و خطاپذیر با جریان‌های خودکار و قابل‌اتکا.'),
         ('نرم‌افزار و مدل‌های اختصاصی', 'software-models.html', 'نرم‌افزار هماهنگ با فرآیند سازمان و مدل‌هایی که روی دادهٔ شما آموزش می‌بینند.')]


def link(name, href):
    return f'<a class="cap-link" href="{href}">جزئیات {name}</a>'


def base():
    return [shell(), group('L-cab', neat_cables(uplink=False)), desks(), aps(), switches(), phone(), rack(('core', 'core2'))]


def layers():
    return ''.join([
        group('l-ai', step(860, 560, 'robot', None, 'step', 56), *[use('chat-circle-dots', x, 524, 22, 'ic talk') for x in (800, 920)]),
        group('l-data', dash(500, 172, 300, 136, 'dash')),
        group('l-auto', *[step(x, 735, ic, None, 'step', 54) for x, ic in [(690, 'plugs-connected'), (560, 'gear-six'), (430, 'database')]],
              arrow('M657 735 H593', 'arw'), arrow('M527 735 H463', 'arw')),
        group('l-sw', path(CURVE, 'proc'), *[step(x, y, ic, None, 'step', 40) for x, y, ic in [(420, 380, 'users'), (700, 545, 'check-circle')]],
              model(285, 740, 'model', 60)),
    ])


def problems():
    return ''.join([
        pin(650, 236, 'دادهٔ پراکنده', 'pin alert', 'files', below=False),
        pin(450, 404, 'کار تکراری و خطاپذیر', 'pin alert', 'repeat', below=False),
        pin(800, 572, 'تصمیم کند', 'pin alert', 'hourglass', below=False),
    ])


def steps3():
    return ''.join([
        chip(400, 205, 'قدم اول', 'chip ok-b s1', 'flag'), chip(600, 375, 'سنجش نتیجه', 'chip ok-b s2', 'gauge'),
        chip(800, 545, 'گسترش', 'chip ok-b s3', 'arrow-fat-right'),
    ])


def build():
    return {
        'file': 'intelligence.html', 'hub': 'intelligence',
        'title': 'هوشمندی: هوش مصنوعی، داده و خودکارسازی | DaranX',
        'description': 'حوزهٔ هوشمندی DaranX: هوش مصنوعی و دستیارهای هوشمند، داده و تحلیل، خودکارسازی هوشمند و نرم‌افزار و مدل‌های اختصاصی؛ از داده‌ای که دارید، تصمیم بهتری بسازید.',
        'keywords': 'هوشمندی سازمانی, هوش مصنوعی, داده و تحلیل, خودکارسازی هوشمند, نرم‌افزار اختصاصی, مدل هوش مصنوعی اختصاصی',
        'crumbs': [('راهکارها', 'solutions.html'), ('هوشمندی', 'intelligence.html')],
        'badge': 'حوزهٔ هوشمندی',
        'h1': 'از داده‌ای که دارید، <em>تصمیم بهتری بسازید</em>',
        'verdict': 'با هوش مصنوعی، تحلیل داده و خودکارسازی، کارهای تکراری را حذف و تصمیم‌ها را دقیق‌تر می‌کنیم؛ متناسب با مسئلهٔ واقعی شما.',
        'cta2': ('چهار بخش هوشمندی', '#a2'),
        'service': {'name': 'هوشمندی سازمان', 'type': 'Business intelligence, AI and automation',
                    'desc': 'استفادهٔ بهتر از داده، خودکارسازی کارهای تکراری و تصمیم‌گیری دقیق‌تر با هوش مصنوعی، تحلیل داده و نرم‌افزارهای اختصاصی.',
                    'parts': [(n, u) for n, u, _ in PARTS]},
        'acts': [
            {'id': 'a1', 'hero': True, 'len': 300, 'label': 'دادهٔ سازمان و تصمیم', 'world': world('a1', base() + [problems()]),
             'caps': [(1, 'p', 'داده دارید، تصمیم نمی‌سازد', 'کار تکراری و خطاپذیر، تصمیمی که کند گرفته می‌شود و داده‌ای که پراکنده و بی‌استفاده مانده است.')],
             'timeline': {'bounds': [.3], 'stops': [0, 1], 'cam': [WIDE]}},
            {'id': 'a2', 'blueprint': True, 'len': 600, 'label': 'چهار بخش هوشمندی', 'world': world('a2', base() + [layers()]),
             'caps': [(0, 'lead', 'فناوری هوشمند، در خدمت نتیجه', 'چهار بخش که با هم طراحی می‌شوند و روی بستری از زیرساخت و دادهٔ درست بنا می‌شوند.')] +
                     [(k + 1, 'h3', n, d, link(n, u)) for k, (n, u, d) in enumerate(PARTS)],
             'timeline': {'bounds': [.18, .38, .58, .78], 'stops': [.08, .28, .48, .68, .9], 'cam': [WIDE]}},
            {'id': 'a3', 'blueprint': True, 'len': 300, 'label': 'هوشمندی گام‌به‌گام', 'world': world('a3', base() + [layers(), steps3(), pulse_line(470, 744, 14, 'mon-p', 'eye')]),
             'caps': [(0, 'lead', 'گام‌به‌گام و قابل‌سنجش', 'یک قدم روشن، سنجش نتیجه و بعد گسترش؛ و پس از استقرار، پایش و بهبود ادامه دارد.'),
                      (1, 'h3', 'همه چیز سر جای درستش.', 'ببینیم داده شما کجا می‌تواند ارزش بسازد.', S.final_actions())],
             'timeline': {'bounds': [.6], 'stops': [.3, 1], 'cam': [WIDE]}},
        ],
        'states': [
            ('a1', '.pin', [1], True, 'opacity:1'),
            ('a2', ':is(.l-ai,.l-data,.l-auto,.l-sw)', [0], True, 'opacity:1'),
            ('a2', '.l-ai', [1], False, 'opacity:1'),
            ('a2', '.l-data', [2], False, 'opacity:1'),
            ('a2', '.l-data .bar', [0, 2], True, 'transform:none'),
            ('a2', '.l-auto', [3], False, 'opacity:1'),
            ('a2', '.l-sw', [4], False, 'opacity:1'),
            ('a3', ':is(.s1,.s2,.s3,.mon-p)', [0, 1], True, 'opacity:1'),
            ('a3', '.l-data .bar', [0, 1], True, 'transform:none'),
        ],
        'css': """.pin{opacity:0;transition:opacity .5s var(--ease-out)}
.l-ai,.l-data,.l-auto,.l-sw{opacity:.1;transition:opacity .6s var(--ease-out)}
#a3 :is(.l-ai,.l-data,.l-auto,.l-sw){opacity:.22}
#a2 :is(.dk-f3-2,.dk-f3-3,.dk-f3-4),#a3 :is(.dk-f3-2,.dk-f3-3,.dk-f3-4){display:none}
.talk{fill:var(--brand)}
.cap-link{display:inline-flex;margin-top:.7rem;font-weight:800;color:var(--brand);border-bottom:1px solid rgba(127,176,223,.5)}""",
        'summary': {'h2': 'هوشمندی سازمان در DaranX',
                    'paras': ['حوزهٔ هوشمندی DaranX شامل راهکارها و دستیارهای هوش مصنوعی، داده و تحلیل، خودکارسازی هوشمند و نرم‌افزار و مدل‌های اختصاصی است؛ برای استفادهٔ بهتر از داده، حذف کارهای تکراری و تصمیم‌گیری دقیق‌تر.',
                              'وقتی زیرساخت ساخته و از آن محافظت شد، نوبت ارزش‌آفرینی است. این فناوری‌ها را نه به‌خاطر مد روز بودن، بلکه برای حل مسئلهٔ واقعی کسب‌وکار به کار می‌گیریم؛ و اگر یک راهکار ساده‌تر کافی باشد، صادقانه همان را می‌گوییم.'],
                    'bullets': ['شروع از شناخت: کدام کارها تکراری و خطاپذیرند، کجا تصمیم‌گیری کند است و چه داده‌ای در اختیار داریم.',
                                'اجزای هوشمندی با هم و روی بستری از زیرساخت و دادهٔ درست طراحی می‌شوند.',
                                'اجرای گام‌به‌گام و قابل‌ارزیابی به‌جای یک جهش بزرگ و پرریسک.', 'پایش و بهبود پس از استقرار، همراه با تغییر داده و فرآیند سازمان.']},
        'parts': {'h2': 'فناوری هوشمند، در خدمت نتیجه', 'items': PARTS},
        'why': ['تصمیم دقیق‌تر', 'افزایش بهره‌وری', 'کاهش خطای انسانی', 'مزیت رقابتی پایدار'],
        'method_h2': 'هوشمندی، از داده تا تصمیم',
        'method': [('شناخت', 'فهم مسئله و داده‌های موجود سازمان.'), ('طراحی', 'طراحی راهکار داده و مدل متناسب با نیاز.'),
                   ('اجرا', 'پیاده‌سازی و یکپارچه‌سازی با فرآیندها.'), ('پشتیبانی', 'پایش نتیجه و بهبود پیوسته مدل‌ها.')],
        'faq': [('حوزهٔ هوشمندی DaranX شامل چه بخش‌هایی است؟', 'راهکارها و دستیارهای هوش مصنوعی، داده و تحلیل، خودکارسازی هوشمند، و نرم‌افزار و مدل‌های اختصاصی.'),
                ('از کجا بدانیم هوشمندی به کار ما می‌آید؟', 'از شناخت شروع می‌کنیم: کدام کارها تکراری و خطاپذیرند، کجا تصمیم‌گیری کند است و چه داده‌ای در اختیار دارید.'),
                ('آیا باید همه چیز را یک‌باره تغییر دهیم؟', 'نه. از یک قدم روشن شروع می‌کنیم، نتیجه را می‌سنجیم و بعد گسترش می‌دهیم.'),
                ('ارزش این راهکارها را چطور می‌سنجید؟', 'با زبان کسب‌وکار: صرفه‌جویی در زمان، کاهش خطا و تصمیمی که به سود سازمان تمام می‌شود.')],
        'related': {'h2': 'حوزه‌های دیگر', 'links': [('زیرساخت', 'infrastructure.html'), ('امنیت', 'security.html'), ('همهٔ راهکارها', 'solutions.html')]},
        'cta': {'h2': 'ببینیم داده شما کجا می‌تواند ارزش بسازد.', 'p': 'یک گفتگو دربارهٔ داده‌ها و فرآیندهای فعلی، نقطهٔ شروع است.'},
    }
