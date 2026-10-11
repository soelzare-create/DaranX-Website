"""داده و تحلیل: از دادهٔ پراکنده تا تصمیم روشن (problem + blueprint + result).
1 every floor keeps its own data apart; reports pile up unread
2 method (blueprint): which decision, collect and clean, the KPIs that matter, a one-glance dashboard
3 result: live data reaches the managers' floor and ends in an action
"""
from kit import *
import layout as S

WIDE = {'z': 1, 'x': 540, 'y': 470}
F3 = {'z': 1.3, 'x': 560, 'y': 260}
SILOS = [('f3', 'مالی', 'currency-circle-dollar'), ('f2', 'فروش', 'shopping-cart'), ('f1', 'عملیات', 'truck')]
SX = 430
DB = (330, 735)
CLEAN = (560, 735)
METRICS = [(540, 0), (600, 1), (660, 0), (720, 0), (780, 1), (840, 0), (900, 1)]   # (x, matters)


def base():
    return [shell(), group('L-cab', neat_cables(uplink=False)), desks(), aps(), switches(), phone(), rack(('core', 'core2'))]


def silos():
    return group('silos', *[silo(SX, ft(f) + 62, ic, t) for f, t, ic in SILOS])


def collect_paths(act, end_x):
    """each silo's data runs down the riser into the basement"""
    s = []
    for k, (f, _, _) in enumerate(SILOS):
        y = ft(f) + 62
        d = f'M{SX + 60} {y} H{RISER - 30} V{fb(f) - 34} H{RISER} V{CORE_Y + 8} H{RACK[0] - 10} V{CLEAN[1]} H{end_x}'
        s.append(mover(f'{act}-pk{k}', d, f'{act}-dot{k}', 8, 'pk bad' if k == 1 else 'pk'))
    return ''.join(s)


def metrics():
    return group('mets', *[group(f'met{" key" if m else ""}', f'<circle cx="{x}" cy="392" r="19"/>', use('chart-line' if m else 'hash', x, 392, 22))
                           for x, m in METRICS])


def a1():
    return world('a1', base() + [
        silos(), pile(865, fb('f3'), 10),
        chip(820, 196, 'گزارشی که کسی نمی‌خواند', 'chip alert unread', 'file-x'),
    ])


def a2():
    return world('a2', base() + [
        asks([('f3', 'کدام تصمیم؟'), ('f2', 'چه داده‌ای لازم است؟'), ('f1', 'منبعش کجاست؟')], x=740), hot(('f3',), 'تصمیم'),
        silos(), collect_paths('a2', CLEAN[0] + 40),
        step(*CLEAN, 'broom', 'پاک‌سازی', 'step clean'), db(*DB, 'dbx store', text='دادهٔ یک‌جا'),
        arrow(f'M{CLEAN[0] - 40} {CLEAN[1]} H{DB[0] + 46}', 'arw', 'a2-cl'),
        metrics(), chip(740, 347, 'فقط شاخص‌هایی که مهم‌اند', 'chip ok-b kpi-c', 'target'),
        dash(500, 172, 300, 136, 'dash d2'),
    ])


def a3():
    return world('a3', base() + [
        silos(), collect_paths('a3', CLEAN[0] + 40), step(*CLEAN, 'broom', None, 'step clean'), db(*DB, 'dbx store'),
        mover('a3-up', f'M{DB[0]} {DB[1] - 36} V{fb("f1") + 20} H{RISER2} V{240} H{500}', 'a3-upd', 9),
        path(f'M{DB[0]} {DB[1] - 36} V{fb("f1") + 20} H{RISER2} V{240} H{500}', 'cab live'),
        dash(500, 172, 300, 136, 'dash d3'),
        chip(680, 372, 'فرصت‌های پنهان', 'chip ok-b opp', 'lightbulb'), chip(680, 545, 'هزینه‌های پنهان', 'chip ok-b cost', 'currency-circle-dollar'),
        chip(350, 125, 'تصمیم و اقدام', 'chip ok-b act', 'flag'),
    ])


def build():
    return {
        'file': 'data-analytics.html', 'hub': 'intelligence',
        'title': 'داده و تحلیل: از دادهٔ پراکنده تا تصمیم روشن | DaranX',
        'description': 'DaranX داده‌های پراکندهٔ مالی، فروش و عملیات را گردآوری و پاک‌سازی می‌کند و با شاخص‌های مهم و داشبوردهای یک‌نگاه، مبنای تصمیم مدیران می‌سازد.',
        'keywords': 'داده و تحلیل, تحلیل داده سازمانی, داشبورد مدیریتی, هوش تجاری, شاخص کلیدی عملکرد, پاک‌سازی داده',
        'crumbs': [('راهکارها', 'solutions.html'), ('هوشمندی', 'intelligence.html'), ('داده و تحلیل', 'data-analytics.html')],
        'badge': 'هوشمندی · داده و تحلیل',
        'h1': 'از دادهٔ پراکنده، <em>تا تصمیم روشن</em>',
        'verdict': 'داده‌های سازمان را به تصویری قابل‌فهم تبدیل می‌کنیم که مبنای تصمیم مدیران باشد، نه گزارشی که کسی نمی‌خواندش.',
        'cta2': ('داده کجا گم می‌شود؟', '#a2'),
        'service': {'name': 'داده و تحلیل', 'type': 'Data analytics and business intelligence',
                    'desc': 'گردآوری، پاک‌سازی و تحلیل داده و ارائهٔ گزارش‌ها و داشبوردهایی که مبنای تصمیم قرار می‌گیرند.'},
        'acts': [
            {'id': 'a1', 'hero': True, 'len': 400, 'label': 'داده‌های پراکندهٔ سازمان', 'world': a1(),
             'caps': [(1, 'p', 'داده هست، تصمیم نیست', 'مالی، فروش و عملیات هر کدام دادهٔ خودشان را جدا نگه می‌دارند و تصویر کامل را کسی نمی‌بیند.'),
                      (2, 'p', 'گزارشی که کسی نمی‌خواند', 'داده‌ای که یک‌جا و قابل‌فهم نشود فقط بار اضافه است و کسی به آن اعتماد نمی‌کند.')],
             'timeline': {'bounds': [.22, .6], 'stops': [0, .42, 1],
                          'cam': [WIDE, [.62, .86, F3]]}},
            {'id': 'a2', 'blueprint': True, 'len': 520, 'label': 'روش DaranX در داده و تحلیل', 'world': a2(),
             'caps': [(0, 'lead', 'اول: کدام تصمیم؟', 'تحلیل بدون هدف نمودار بی‌استفاده می‌سازد؛ اول تصمیم را روشن می‌کنیم، بعد سراغ داده می‌رویم.'),
                      (1, 'h3', 'گردآوری و پاک‌سازی', 'داده از منابع مختلف یک‌جا جمع و پاک می‌شود، چون دادهٔ نادرست به تصمیم نادرست می‌رسد.'),
                      (2, 'h3', 'شاخص‌هایی که واقعاً مهم‌اند', 'نه هر عددی که قابل اندازه‌گیری است؛ فقط شاخص‌هایی که به تصمیم کمک می‌کنند.'),
                      (3, 'h3', 'داشبورد در یک نگاه', 'مدیر به‌جای غرق شدن در جزئیات، تصویر روشنی از وضعیت و روند پیش رو دارد.')],
             'timeline': {'bounds': [.24, .5, .74], 'stops': [.1, .37, .62, .9],
                          'cam': [WIDE],
                          'dots': [['a2-pk0', 'a2-dot0', .26, .44], ['a2-pk1', 'a2-dot1', .3, .47], ['a2-pk2', 'a2-dot2', .34, .5]],
                          'draws': [['a2-cl', .4, .5]]}},
            {'id': 'a3', 'blueprint': True, 'len': 340, 'label': 'از داده تا اقدام', 'world': a3(),
             'caps': [(0, 'lead', 'از داده تا اقدام', 'گزارش‌ها زنده و قابل‌اتکا می‌مانند و فرصت‌ها و هزینه‌های پنهان در داده آشکار می‌شوند.'),
                      (1, 'h3', 'همه چیز سر جای درستش.', 'داده و تحلیل را برای تصمیم‌های واقعی شما طراحی کنیم.', S.final_actions())],
             'timeline': {'bounds': [.62], 'stops': [.34, 1],
                          'cam': [WIDE],
                          'dots': [['a3-pk0', 'a3-dot0', .02, .2], ['a3-pk1', 'a3-dot1', .05, .22], ['a3-pk2', 'a3-dot2', .08, .24], ['a3-up', 'a3-upd', .2, .34]]}},
        ],
        'states': [
            ('a1', '.silo-b', [1, 2], True, 'opacity:1'),
            ('a1', '.silos .chip', [1, 2], True, 'opacity:1'),
            ('a1', '.sheet', [2], True, 'opacity:1;transform:none'),
            ('a1', '.unread', [2], True, 'opacity:1'),
            ('a2', ':is(.ask,.hot,.hot-t)', [0], True, 'opacity:1'),
            ('a2', '.silos .chip', [0, 1, 2, 3], True, 'opacity:1'),
            ('a2', '.pk', [1], False, 'opacity:1'),
            ('a2', ':is(.clean,.store,.arw)', [1, 2, 3], True, 'opacity:1'),
            ('a2', '.silo-b', [0], True, 'opacity:1'),
            ('a2', '.silo-b', [1, 2, 3], False, 'opacity:0'),
            ('a2', '.mets', [2], True, 'opacity:1'),
            ('a2', '.met:not(.key)', [2], False, 'opacity:.22'),
            ('a2', '.met.key circle', [2], True, 'stroke:var(--brand);fill:rgba(127,176,223,.2)'),
            ('a2', '.kpi-c', [2], True, 'opacity:1'),
            ('a2', '.d2', [3], True, 'opacity:1'),
            ('a2', '.d2 .bar', [3], True, 'transform:none'),
            ('a2', ':is(.dk-f3-2,.dk-f3-3,.dk-f3-4)', [3], True, 'opacity:0'),
            ('a3', '.pk', [0], False, 'opacity:1'),
            ('a3', ':is(.silos .chip,.clean,.store)', [0, 1], True, 'opacity:1'),
            ('a3', ':is(.dk-f3-2,.dk-f3-3,.dk-f3-4)', [0, 1], True, 'opacity:0'),
            ('a3', '.d3 .bar', [0, 1], True, 'transform:none'),
            ('a3', ':is(.opp,.cost,.act)', [0, 1], True, 'opacity:1'),
            ('a3', '.live', [0, 1], True, 'stroke:var(--brand);stroke-dasharray:4 14;animation:flow 1.1s linear infinite'),
        ],
        'css': """.silos .chip,.silo-b,.unread,.opp,.cost,.act,.clean,.store,.arw,.mets,.d2{opacity:0;transition:opacity .5s var(--ease-out)}
.met circle{fill:rgba(8,22,39,.85);stroke:#58779B;stroke-width:1.5;transition:fill .4s ease,stroke .4s ease}
.met .ic{fill:var(--st-ink-2)}.met.key .ic{fill:var(--brand)}
.met{transition:opacity .5s var(--ease-out)}
.desk{transition:opacity .4s ease}
.cab.live{stroke-width:3.5;fill:none}""",
        'summary': {'h2': 'داده و تحلیل در DaranX',
                    'paras': ['DaranX داده‌های سازمان را گردآوری، پاک‌سازی و تحلیل می‌کند و گزارش‌ها و داشبوردهایی می‌سازد که مبنای تصمیم مدیران قرار می‌گیرند.',
                              'بیشتر سازمان‌ها داده دارند، اما کمتر سازمانی از داده‌اش تصمیم می‌سازد. داده‌های پراکنده در سیستم‌های مالی، فروش و عملیات تا وقتی یک‌جا و قابل‌فهم نشوند، فقط بار اضافه‌اند. تحلیل درست به یک تصمیم مشخص و یک اقدام ختم می‌شود.'],
                    'bullets': ['شروع از یک پرسش: چه تصمیمی قرار است بر اساس داده گرفته شود؟', 'گردآوری و پاک‌سازی داده از منابع مختلف.',
                                'تمرکز بر شاخص‌هایی که واقعاً مهم‌اند، نه هر عددی که قابل اندازه‌گیری است.', 'داشبورد و گزارش‌هایی که در یک نگاه قابل‌فهم‌اند.',
                                'گزارش‌های زنده و قابل‌اتکا که با رشد سازمان به‌روز می‌شوند.']},
        'why': ['تصمیم داده‌محور', 'شفافیت بر عملکرد', 'کشف فرصت و کاهش هزینه'],
        'method': [('شناخت', 'تصمیم‌هایی که باید گرفته شوند و داده‌های لازم برای آن‌ها.'), ('طراحی', 'گردآوری، پاک‌سازی، شاخص‌ها و داشبورد.'),
                   ('اجرا', 'گزارش‌های زنده و قابل‌اتکا که با رشد سازمان به‌روز می‌شوند.'), ('پشتیبانی', 'نگهداری داده به‌عنوان یک قابلیت همیشگی.')],
        'faq': [('داده و تحلیل از کجا شروع می‌شود؟', 'از این پرسش که چه تصمیمی قرار است بر اساس داده گرفته شود؛ بعد سراغ داده‌های لازم و منابع آن‌ها می‌رویم.'),
                ('داده‌های ما در سیستم‌های مختلف پراکنده است. مشکلی نیست؟', 'نه. گردآوری و پاک‌سازی داده از منابع مختلف مثل مالی، فروش و عملیات بخشی از طراحی است.'),
                ('خروجی کار چیست؟', 'داشبورد و گزارش‌هایی که در یک نگاه قابل‌فهم‌اند و بر شاخص‌های واقعاً مهم تمرکز دارند.'),
                ('گزارش‌ها بعد از تحویل به‌روز می‌مانند؟', 'بله. دادهٔ خوب یک پروژهٔ یک‌باره نیست؛ گزارش‌ها زنده می‌مانند و پشتیبانی ادامه دارد.')],
        'related': {'h2': 'بقیهٔ حوزهٔ هوشمندی', 'links': [('راهکارها و دستیارهای هوش مصنوعی', 'ai-solutions.html'), ('خودکارسازی هوشمند', 'automation.html'),
                                                          ('نرم‌افزار و مدل‌های اختصاصی', 'software-models.html'), ('همهٔ حوزهٔ هوشمندی', 'intelligence.html')]},
        'cta': {'h2': 'داده و تحلیل را برای تصمیم‌های واقعی شما طراحی کنیم.', 'p': 'اول تصمیم‌ها و داده‌های شما را مشخص می‌کنیم، بعد می‌گوییم چه چیزی واقعاً لازم است.'},
    }
