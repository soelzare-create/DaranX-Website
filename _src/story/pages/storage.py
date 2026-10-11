"""ذخیره‌سازی: داده‌ها امن و همیشه در دسترس (journey + before/after).
1 every floor's data lands in one place; one disk fails and the data is gone
2 method (blueprint): which data matters, tiers, redundancy, backup and restore
3 result: capacity grows without interruption
"""
from kit import *
import layout as S

WIDE = {'z': 1, 'x': 540, 'y': 470}
ROOM = {'z': 1.4, 'x': 560, 'y': 742}
DOCS = [('receipt', 420, 222, 'f3'), ('folder-simple', 650, 222, 'f3'), ('users-three', 530, 392, 'f2'), ('files', 650, 392, 'f2'), ('file-text', 650, 562, 'f1')]


def docs():
    return group('docs', *[group(f'doc doc-{f}', use(ic, x, y, 26)) for ic, x, y, f in DOCS])


def flow_paths(act):
    s = []
    for k, (fid, i) in enumerate([('f3', 0), ('f2', 2), ('f1', 1)]):
        px, py = desk_port(fid, i)
        d = f'M{px} {py} V{fb(fid) - 8} H{RISER - 18} V{fb(fid) - 34} H{RISER} V{CORE_Y + 8} H{560}'
        s.append(mover(f'{act}-pk{k}', d, f'{act}-dot{k}', 8))
    return ''.join(s)


def base():
    return [shell(), group('L-cab', neat_cables(uplink=False)), desks(), aps(), switches(), cameras(), phone(), rack(('core', 'core2'))]


def a1():
    return world('a1', base() + [
        disks(470, 712, cols=2, rows=2, cls='disks one', bad=(1,)), docs(), flow_paths('a1'),
        xmark(538, 738, 16), chip(560, 842, 'داده در دسترس نیست', 'chip alert lost', 'warning'),
    ])


def a2():
    return world('a2', base() + [
        asks([('f3', 'کدام داده حیاتی است؟'), ('f2', 'حجم امروز چقدر است؟'), ('f1', 'با چه سرعتی رشد می‌کند؟'), ('b', 'کدام نیاز به دسترسی سریع دارد؟')], x=740),
        hot(('b',)), docs(),
        disks(430, 700, cols=4, rows=2, cls='disks arr', bad=(5,)),
        group('tiers', chip(380, 842, 'دسترسی سریع', 'chip', 'lightning'), chip(640, 842, 'نگهداری بلندمدت', 'chip', 'clock-counter-clockwise')),
        xmark(538, 768, 15), chip(600, 842, 'داده سالم است', 'chip ok-b', 'check-circle'),
        group('bkp', '<rect class="rk" x="244" y="712" width="104" height="80" rx="8"/>', use('database', 296, 750, 40), label(296, 830, 'نسخهٔ پشتیبان', 'lbl sm')),
        f'<g class="bk-arrows">{arrow("M426 730 H356", "arw b-out", "a2-bout")}{arrow("M356 774 H426", "arw b-in", "a2-bin")}</g>',
    ])


def a3():
    return world('a3', base() + [
        docs(), disks(430, 700, cols=4, rows=2, cls='disks arr'), disks(600, 700, cols=4, rows=2, cls='disks shelf2'),
        flow_paths('a3'),
        group('capg', gauge(300, 830, 560, .5, 'gauge g1', id='a3-g1'), label(714, 884, 'ظرفیت', 'lbl sm')),
    ])


def build():
    return {
        'file': 'storage.html', 'hub': 'infrastructure',
        'title': 'ذخیره‌سازی سازمانی امن و در دسترس | DaranX',
        'description': 'DaranX ذخیره‌سازی داده‌های سازمان را با ظرفیت، کارایی و دسترس‌پذیری متناسب با اهمیت داده طراحی می‌کند؛ با افزونگی، پشتیبان‌گیری، بازیابی و رشد بدون اختلال.',
        'keywords': 'ذخیره‌سازی سازمانی, استوریج, افزونگی دیسک, پشتیبان‌گیری, بازیابی داده, ظرفیت ذخیره‌سازی',
        'crumbs': [('راهکارها', 'solutions.html'), ('زیرساخت', 'infrastructure.html'), ('ذخیره‌سازی', 'storage.html')],
        'badge': 'زیرساخت · ذخیره‌سازی',
        'h1': 'داده‌ها؛ <em>امن و همیشه در دسترس</em>',
        'verdict': 'ذخیره‌سازی را متناسب با اهمیت واقعی داده‌ها طراحی می‌کنیم، نه یک هارد بزرگ‌تر دیگر.',
        'cta2': ('یک دیسک چه می‌کند؟', '#a2'),
        'service': {'name': 'طراحی راهکار ذخیره‌سازی سازمانی', 'type': 'Enterprise storage design',
                    'desc': 'طراحی راهکار ذخیره‌سازی با ظرفیت، کارایی و سطح دسترس‌پذیری متناسب با اهمیت داده‌ها.'},
        'acts': [
            {'id': 'a1', 'hero': True, 'len': 400, 'label': 'داده‌های سازمان و جایی که نگهداری می‌شوند', 'world': a1(),
             'caps': [(1, 'p', 'همهٔ داده‌ها به یک‌جا می‌رسند', 'اسناد مالی، پرونده‌ها، نسخه‌های پشتیبان و سوابق مشتریان روی یک بستر نگهداری می‌شوند.'),
                      (2, 'p', 'تا روزی که از دست نرود، دیده نمی‌شود', 'لحظه‌ای که داده در دسترس نباشد یا از دست برود، اهمیتش به گران‌ترین شکل روشن می‌شود.')],
             'timeline': {'bounds': [.22, .6], 'stops': [0, .42, 1],
                          'cam': [WIDE, [.62, .86, ROOM]],
                          'dots': [['a1-pk0', 'a1-dot0', .2, .45], ['a1-pk1', 'a1-dot1', .26, .5], ['a1-pk2', 'a1-dot2', .3, .55]]}},
            {'id': 'a2', 'blueprint': True, 'len': 500, 'label': 'روش DaranX در طراحی ذخیره‌سازی', 'world': a2(),
             'caps': [(0, 'lead', 'اول اهمیت داده را می‌شناسیم', 'کدام داده حیاتی است، حجمش چقدر است و با چه سرعتی رشد می‌کند؟'),
                      (1, 'h3', 'لایه‌بندی درست داده', 'داده‌ای که دسترسی سریع لازم دارد جدا از داده‌ای که فقط باید سال‌ها سالم بماند.'),
                      (2, 'h3', 'یک دیسک خراب، داده سالم', 'افزونگی باعث می‌شود خرابی یک دیسک به از دست رفتن اطلاعات منجر نشود.'),
                      (3, 'h3', 'پشتیبان‌گیری و بازیابی', 'آمادگی برای پشتیبان‌گیری و بازیابی پس از حادثه، از همان طراحی.')],
             'timeline': {'bounds': [.25, .5, .75], 'stops': [.1, .37, .62, .9],
                          'cam': [WIDE, [.27, .4, ROOM]],
                          'draws': [['a2-bout', .77, .87], ['a2-bin', .86, .96]]}},
            {'id': 'a3', 'blueprint': True, 'len': 320, 'label': 'رشد ظرفیت ذخیره‌سازی بدون اختلال', 'world': a3(),
             'caps': [(0, 'lead', 'رشد ظرفیت، بدون اختلال', 'قفسهٔ تازه به همان طراحی اضافه می‌شود و سرویس‌ها بدون توقف کار می‌کنند.'),
                      (1, 'h3', 'همه چیز سر جای درستش.', 'ذخیره‌سازی را برای مسئلهٔ واقعی شما طراحی کنیم.', S.final_actions())],
             'timeline': {'bounds': [.62], 'stops': [.32, 1],
                          'cam': [ROOM, [.66, .86, WIDE]],
                          'dots': [['a3-pk0', 'a3-dot0', .02, .3], ['a3-pk1', 'a3-dot1', .06, .32], ['a3-pk2', 'a3-dot2', .1, .34]],
                          'vars': [['a3-g1', 't', .04, .3]]}},
        ],
        'states': [
            ('a1', '.pk', [1], False, 'opacity:1'),
            ('a1', ':is(.flow,.riser)', [1], True, 'stroke:var(--brand);stroke-dasharray:4 14;animation:flow 1.1s linear infinite'),
            ('a1', '.docs', [1, 2], True, 'opacity:1'),
            ('a1', '.disk.bad', [2], True, 'fill:var(--alert)'),
            ('a1', ':is(.brk,.lost)', [2], True, 'opacity:1'),
            ('a1', '.doc', [2], False, 'opacity:.25'),
            ('a2', ':is(.ask,.hot,.hot-t,.docs)', [0], True, 'opacity:1'),
            ('a2', '.arr', [1, 2, 3], True, 'opacity:1'),
            ('a2', '.tiers', [1], True, 'opacity:1'),
            ('a2', '.arr .disk:nth-child(-n+5)', [1], True, 'fill:#4F8AC4'),
            ('a2', '.disk.bad', [2], True, 'fill:var(--alert)'),
            ('a2', ':is(.brk,.ok-b)', [2], True, 'opacity:1'),
            ('a2', ':is(.bkp,.bk-arrows)', [3], True, 'opacity:1'),
            ('a3', '.pk', [0], False, 'opacity:1'),
            ('a3', '.shelf2', [0, 1], True, 'opacity:1;transform:none'),
            ('a3', '.capg', [0, 1], True, 'opacity:1'),
            ('a3', ':is(.flow,.riser)', [0], True, 'stroke:var(--brand);stroke-dasharray:4 14;animation:flow 1.1s linear infinite'),
        ],
        'css': """.docs,.lost,.brk,.ok-b,.tiers,.bkp,.bk-arrows,.capg,.arr{opacity:0;transition:opacity .5s var(--ease-out)}
.doc .ic{fill:var(--brand)}.doc{transition:opacity .5s ease;transition-delay:calc(var(--k,0) * 80ms)}
.shelf2{opacity:0;transform:translateY(40px);transition:opacity .6s var(--ease-out),transform .9s var(--ease-out)}
.disks .disk{transition:fill .5s ease}""",
        'summary': {'h2': 'ذخیره‌سازی امن و در دسترس در DaranX',
                    'paras': ['DaranX راهکار ذخیره‌سازی سازمان را با ظرفیت، کارایی و سطح دسترس‌پذیری متناسب با اهمیت داده‌ها طراحی، اجرا و پشتیبانی می‌کند.',
                              'اسناد مالی، پرونده‌ها، نسخه‌های پشتیبان و سوابق مشتریان به بستری نیاز دارند که هم امن باشد و هم همیشه در دسترس. ذخیره‌سازی خوب فقط فضای بیشتر نیست؛ اطمینان از این است که داده در زمان لازم، سالم و در دسترس باشد.'],
                    'bullets': ['شناخت داده‌های حیاتی، حجم امروز و سرعت رشد.', 'لایه‌بندی درست بین داده‌ای که دسترسی سریع لازم دارد و داده‌ای که باید مدت‌ها سالم بماند.',
                                'افزونگی و محافظت داده در برابر خرابی سخت‌افزار.', 'کارایی متناسب با نیاز تا سرویس‌ها کند نشوند.', 'آمادگی برای پشتیبان‌گیری و بازیابی پس از حادثه، و رشد ظرفیت بدون اختلال.']},
        'why': ['در دسترس بودن داده‌ها', 'حفاظت در برابر از دست رفتن داده', 'رشد ظرفیت بدون اختلال'],
        'method': [('شناخت', 'اهمیت، حجم و سرعت رشد داده‌ها را می‌سنجیم.'), ('طراحی', 'ظرفیت، کارایی، لایه‌بندی، افزونگی و مسیر پشتیبان‌گیری.'),
                   ('اجرا', 'پیاده‌سازی و مستندسازی برای رشد بدون اختلال.'), ('پشتیبانی', 'نگهداری و توسعهٔ ذخیره‌سازی پس از تحویل.')],
        'faq': [('ظرفیت ذخیره‌سازی را چطور تعیین می‌کنید؟', 'از حجم داده امروز و سرعت رشد آن، و اینکه کدام داده‌ها حیاتی‌اند و کدام نیاز به دسترسی سریع دارند.'),
                ('اگر یک دیسک خراب شود چه می‌شود؟', 'با افزونگی، خرابی یک دیسک به از دست رفتن اطلاعات منجر نمی‌شود.'),
                ('پشتیبان‌گیری و بازیابی هم جزو طراحی است؟', 'بله. آمادگی برای پشتیبان‌گیری و بازیابی پس از حادثه از همان ابتدا در نظر گرفته می‌شود.'),
                ('بعداً می‌توانیم ظرفیت را بیشتر کنیم؟', 'بله. اجرا و مستندسازی طوری انجام می‌شود که رشد ظرفیت بدون اختلال و بدون بازطراحی پرهزینه ممکن باشد.')],
        'related': {'h2': 'بقیهٔ حوزهٔ زیرساخت', 'links': [('شبکه', 'network.html'), ('دیتاسنتر و سرور', 'datacenter-server.html'), ('برق و UPS', 'power-ups.html'),
                                                          ('زیرساخت پسیو', 'passive.html'), ('همهٔ حوزهٔ زیرساخت', 'infrastructure.html')]},
        'cta': {'h2': 'ذخیره‌سازی را برای مسئلهٔ واقعی شما طراحی کنیم.', 'p': 'اول نیاز شما را مشخص می‌کنیم، بعد می‌گوییم چه چیزی واقعاً لازم دارید.'},
    }
