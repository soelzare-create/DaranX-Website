"""خودکارسازی هوشمند: حذف کارهای دستی و خطاپذیر (problem + blueprint + result).
1 the same paper hops desk to desk every day; one slip and it starts again
2 method (blueprint): understand the process, simplify it, an integrated automated flow, people keep the important decisions
3 result: the flow runs by itself and people get their time back
"""
from kit import *
import layout as S

WIDE = {'z': 1, 'x': 540, 'y': 470}
F2 = {'z': 1.3, 'x': 560, 'y': 420}
BAND = 378                                   # f2: the free band above the monitors
HOPS = [[x + 40, BAND] for x in reversed(DESKS['f2'])]     # reading order: right to left
PIPE_Y = 735
PIPE = [(690, 'plugs-connected'), (545, 'gear-six'), (400, 'database')]
MESSY = [(880, 366), (820, 396), (760, 362), (700, 398), (640, 360), (600, 396), (520, 364), (470, 398), (420, 370)]


def base():
    return [shell(), group('L-cab', neat_cables(uplink=False)), desks(), aps(), switches(), phone(), rack(('core', 'core2'))]


def doc(act, cls='docm'):
    return f'<g class="{cls}" id="{act}-doc">{use("file-text", 0, 0, 28)}</g>'


def loops():
    return group('loops', *[loop(x, BAND - 2, 30) for x, _ in HOPS])


def pipeline(act):
    s = [step(x, PIPE_Y, ic, None, 'step') for x, ic in PIPE]
    s.append(arrow(f'M{PIPE[0][0] - 36} {PIPE_Y} H{PIPE[1][0] + 40}', 'arw', f'{act}-p1'))
    s.append(arrow(f'M{PIPE[1][0] - 36} {PIPE_Y} H{PIPE[2][0] + 40}', 'arw', f'{act}-p2'))
    s.append(mover(f'{act}-pp', f'M{RACK[0] - 10} {PIPE_Y} H{PIPE[2][0]}', f'{act}-pd', 7))
    return group('pipe', *s)


def a1():
    return world('a1', base() + [
        loops(), chip(760, 342, 'هر روز، دستی', 'chip daily', 'clock'),
        doc('a1'),
        group('slip', use('file-text', 450, BAND, 28, 'ic alert-ic'), xmark(450, BAND, 16)),
        path(f'M450 {BAND - 20} C520 300 760 300 {HOPS[0][0]} {BAND - 20}', 'proc bad redo', ' pathLength="1" id="a1-redo"'),
        chip(660, 455, 'خطا و دوباره‌کاری', 'chip alert slip-c', 'warning'),
    ])


def a2():
    messy = 'M' + ' L'.join(f'{x} {y}' for x, y in MESSY)
    return world('a2', base() + [
        asks([('f3', 'کدام کار تکراری است؟'), ('f1', 'به چه سیستمی وصل است؟')], x=720),
        group('mess', path(messy, 'proc bad', ' pathLength="1"'), *[f'<circle class="mdot" cx="{x}" cy="{y}" r="7"/>' for x, y in MESSY]),
        group('simple', path(f'M880 {BAND} H420', 'proc', ' pathLength="1" id="a2-simple"'),
              *[step(x, BAND, ic, None, 'step', 40) for x, ic in [(860, 'file-text'), (650, 'gear-six'), (440, 'check-circle')]]),
        pipeline('a2'),
        chip(600, 842, 'یکپارچه با سیستم‌های موجود', 'chip ok-b integ', 'plugs-connected'),
        path(f'M{PIPE[1][0]} {PIPE_Y - 34} C{PIPE[1][0]} 560 700 420 700 228', 'acc hum-l'),
        chip(700, 205, 'تصمیم مهم با انسان', 'chip ok-b hum', 'user-check'),
        chip(290, 842, 'کنترل و پایش', 'chip ok-b mon', 'eye'),
    ])


def a3():
    return world('a3', base() + [
        pipeline('a3'),
        group('ideas', *[use('lightbulb', x, BAND - 2, 28, 'ic') for x, _ in HOPS]),
        chip(700, 205, 'تصمیم مهم با انسان', 'chip ok-b hum', 'user-check'),
        chip(600, 842, 'کار تکراری با جریان خودکار', 'chip ok-b integ', 'gear-six'),
    ])


def build():
    return {
        'file': 'automation.html', 'hub': 'intelligence',
        'title': 'خودکارسازی هوشمند فرآیندهای سازمان | DaranX',
        'description': 'DaranX کارهای تکراری و خطاپذیر سازمان را می‌شناسد، ساده می‌کند و با جریان‌های خودکار یکپارچه با سیستم‌های موجود جایگزین می‌کند؛ تصمیم مهم با انسان می‌ماند.',
        'keywords': 'خودکارسازی هوشمند, اتوماسیون فرآیند, حذف کار دستی, کاهش خطای انسانی, یکپارچه‌سازی سیستم‌ها, بهره‌وری سازمان',
        'crumbs': [('راهکارها', 'solutions.html'), ('هوشمندی', 'intelligence.html'), ('خودکارسازی هوشمند', 'automation.html')],
        'badge': 'هوشمندی · خودکارسازی',
        'h1': 'کار تکراری را <em>به جریان خودکار بسپارید</em>',
        'verdict': 'فرآیندهای تکراری را با جریان‌های خودکار قابل‌اتکا و یکپارچه با سیستم‌های موجود جایگزین می‌کنیم.',
        'cta2': ('کار تکراری چه می‌کند؟', '#a2'),
        'service': {'name': 'خودکارسازی هوشمند', 'type': 'Intelligent process automation',
                    'desc': 'خودکارسازی فرآیندهای تکراری سازمان با تمرکز بر پایداری، یکپارچگی و کاهش دخالت دستی.'},
        'acts': [
            {'id': 'a1', 'hero': True, 'len': 400, 'label': 'کار تکراری و دستی در سازمان', 'world': a1(),
             'caps': [(1, 'p', 'همان کار، هر روز', 'بخش زیادی از وقت سازمان صرف کار تکراری و دستی می‌شود؛ کاری که انگیزهٔ کارکنان را هم می‌گیرد.'),
                      (2, 'p', 'یک خطا، دوباره از اول', 'کار دستی مستعد خطاست و هر خطا یعنی دوباره‌کاری و وقتی که دوباره صرف می‌شود.')],
             'timeline': {'bounds': [.22, .6], 'stops': [0, .45, 1],
                          'cam': [WIDE, [.62, .84, F2]],
                          'moves': [['a1-doc', HOPS, .22, .58]],
                          'draws': [['a1-redo', .72, .9]]}},
            {'id': 'a2', 'blueprint': True, 'len': 560, 'label': 'روش DaranX در خودکارسازی', 'world': a2(),
             'caps': [(0, 'lead', 'اول فرآیند را می‌فهمیم', 'کدام کار تکراری است و به چه سیستمی وصل است؟ خودکار کردن فرآیند اشتباه، فقط اشتباه را سریع‌تر می‌کند.'),
                      (1, 'h3', 'اول ساده، بعد خودکار', 'فرآیند را درست می‌فهمیم و در صورت نیاز ساده‌اش می‌کنیم.'),
                      (2, 'h3', 'جریان خودکار و یکپارچه', 'جریانی پایدار که به سیستم‌های موجود وصل است و جزیره‌ای جدا نمی‌ماند.'),
                      (3, 'h3', 'تصمیم مهم با انسان', 'کار تکراری حذف می‌شود، نه تصمیم مهم؛ کنترل و پایش درستی نتیجه را تضمین می‌کند.')],
             'timeline': {'bounds': [.24, .5, .74], 'stops': [.1, .37, .62, .9],
                          'cam': [WIDE],
                          'draws': [['a2-simple', .27, .4], ['a2-p1', .52, .58], ['a2-p2', .57, .63]],
                          'dots': [['a2-pp', 'a2-pd', .6, .72]]}},
            {'id': 'a3', 'blueprint': True, 'len': 340, 'label': 'نتیجهٔ خودکارسازی', 'world': a3(),
             'caps': [(0, 'lead', 'وقت آزاد برای کار مهم‌تر', 'بهره‌وری بیشتر، خطا و دوباره‌کاری کمتر، و کارکنانی که انرژی‌شان صرف رشد می‌شود.'),
                      (1, 'h3', 'همه چیز سر جای درستش.', 'خودکارسازی را برای فرآیند واقعی شما طراحی کنیم.', S.final_actions())],
             'timeline': {'bounds': [.62], 'stops': [.34, 1],
                          'cam': [WIDE],
                          'dots': [['a3-pp', 'a3-pd', .04, .3]]}},
        ],
        'states': [
            ('a1', ':is(.loops,.daily)', [1, 2], True, 'opacity:1'),
            ('a1', '.docm', [1, 2], False, 'opacity:1'),
            ('a1', ':is(.slip,.slip-c,.redo)', [2], True, 'opacity:1'),
            ('a2', ':is(.ask,.mess)', [0], True, 'opacity:1'),
            ('a2', '.mess', [1], False, 'opacity:.15'),
            ('a2', '.simple', [1, 2, 3], True, 'opacity:1'),
            ('a2', ':is(.pipe,.integ)', [2, 3], True, 'opacity:1'),
            ('a2', '.pk', [2], False, 'opacity:1'),
            ('a2', ':is(.hum,.hum-l,.mon)', [3], True, 'opacity:1'),
            ('a3', ':is(.pipe,.ideas,.hum,.integ)', [0, 1], True, 'opacity:1'),
            ('a3', '.pk', [0], False, 'opacity:1'),
        ],
        'css': """.loops,.daily,.docm,.slip,.slip-c,.redo,.mess,.simple,.pipe,.hum-l,.ideas{opacity:0;transition:opacity .5s var(--ease-out)}
.docm .ic{fill:#E9F2FB}
.mdot{fill:#E2A35A}
.ideas .ic{fill:#F5D27A}
.hum-l{stroke-dasharray:6 8}""",
        'summary': {'h2': 'خودکارسازی هوشمند در DaranX',
                    'paras': ['DaranX فرآیندهای تکراری سازمان را با جریان‌های خودکار پایدار و یکپارچه با سیستم‌های موجود جایگزین می‌کند تا نیروی انسانی روی کاری تمرکز کند که واقعاً ارزش می‌سازد.',
                              'کارهای تکراری و دستی هم وقت می‌گیرند و هم مستعد خطا هستند. خودکارسازی را متناسب با فرآیند واقعی شما طراحی می‌کنیم، نه بر اساس یک الگوی آماده؛ و جایی که قضاوت انسانی لازم است، انسان را از مدار خارج نمی‌کنیم.'],
                    'bullets': ['شناخت فرآیندهای تکراری و قاعده‌مند و جایی که خطای انسانی بیشترین هزینه را دارد.', 'ساده‌سازی فرآیند پیش از خودکارسازی.',
                                'جریان‌های خودکار پایدار و یکپارچه با سیستم‌های موجود.', 'کنترل و پایش برای اطمینان از درستی نتیجه.',
                                'اجرای گام‌به‌گام با کمترین اختلال در کار جاری.']},
        'why': ['افزایش بهره‌وری', 'کاهش خطا و دوباره‌کاری', 'تمرکز نیروی انسانی بر کار ارزشمند'],
        'method': [('شناخت', 'فرآیندهای تکراری، ورودی و خروجی و سیستم‌های مرتبط.'), ('طراحی', 'ساده‌سازی فرآیند و طراحی جریان خودکار یکپارچه.'),
                   ('اجرا', 'پیاده‌سازی گام‌به‌گام با کمترین اختلال.'), ('پشتیبانی', 'به‌روز ماندن خودکارسازی همراه با تغییر فرآیندها.')],
        'faq': [('چه کارهایی را می‌شود خودکار کرد؟', 'کارهای تکراری و قاعده‌مند که وقت می‌گیرند و مستعد خطا هستند؛ این را در مرحلهٔ شناخت با هم مشخص می‌کنیم.'),
                ('آیا خودکارسازی جای کارکنان را می‌گیرد؟', 'نه. کار تکراری حذف می‌شود، نه تصمیم مهم؛ جایی که قضاوت انسانی لازم است، انسان در مدار می‌ماند.'),
                ('با سیستم‌های فعلی ما کار می‌کند؟', 'بله. خودکارسازی با سیستم‌های موجود یکپارچه طراحی می‌شود تا جزیره‌ای جدا نماند.'),
                ('اگر فرآیندهای ما تغییر کند چه می‌شود؟', 'پشتیبانی ادامه دارد و خودکارسازی همراه با تغییر فرآیندهای سازمان به‌روز می‌شود.')],
        'related': {'h2': 'بقیهٔ حوزهٔ هوشمندی', 'links': [('راهکارها و دستیارهای هوش مصنوعی', 'ai-solutions.html'), ('داده و تحلیل', 'data-analytics.html'),
                                                          ('نرم‌افزار و مدل‌های اختصاصی', 'software-models.html'), ('همهٔ حوزهٔ هوشمندی', 'intelligence.html')]},
        'cta': {'h2': 'خودکارسازی را برای فرآیند واقعی شما طراحی کنیم.', 'p': 'اگر جایی خودکارسازی ارزشی نسازد، صادقانه همان را می‌گوییم.'},
    }
