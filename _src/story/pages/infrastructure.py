"""زیرساخت (hub): زیرساختی که سازمان با خیال راحت روی آن می‌ایستد (assemble).
1 everything stands on one base; when it is not built right, problems spread
2 the five parts, one per scene, each linking to its page
3 designed together, not placed side by side
"""
from kit import *
import layout as S

WIDE = {'z': 1, 'x': 540, 'y': 470}
NAMES = {'b': 'اتاق سرور'}
PARTS = [('شبکه', 'network.html', 'طراحی و پیاده‌سازی شبکه‌ای پایدار و مقیاس‌پذیر؛ بستر ارتباط همهٔ سرویس‌ها و کاربران.'),
         ('دیتاسنتر و سرور', 'datacenter-server.html', 'مرکز داده و سرورهایی که متناسب با بار واقعی سازمان انتخاب و پیکربندی می‌شوند.'),
         ('ذخیره‌سازی', 'storage.html', 'نگهداری امن و در دسترس داده‌ها با ظرفیت و کارایی درست، متناسب با رشد آینده.'),
         ('برق و UPS', 'power-ups.html', 'تغذیهٔ پایدار و حفاظت در برابر قطعی برق؛ پیش‌نیاز در دسترس بودن همیشگی سرویس‌ها.'),
         ('زیرساخت پسیو', 'passive.html', 'کابل‌کشی و بستر فیزیکی منظم و استاندارد که عیب‌یابی، نگهداری و توسعه را ساده می‌کند.')]


def link(name, href):
    return f'<a class="cap-link" href="{href}">جزئیات {name}</a>'


def parts_layer():
    trays = [f'<rect class="tray" x="{X0 + 20}" y="{fb(f) - 14}" width="{RISER - X0 - 20}" height="10" rx="3"/>' for f, _, _, _ in FLOORS]
    power = [path('M168 508 C190 540 196 600 196 640 V722 H380', 'wire pw pw-in'), path('M380 722 H226 V196', 'wire pw')] + \
            [path(f'M226 {top + 46} H930', 'wire pw') for _, top, _, _ in FLOORS]
    return ''.join([
        group('p-net', neat_cables(), aps(), switches(), rack(), cloud()),
        group('p-dc', srack(490, cls='srack'), srack(575, cls='srack')),
        group('p-st', disks(655, 712, cols=3, rows=2)),
        group('p-pw', *power, ups(380, 708),
              group('pole', '<line class="pl" x1="150" y1="500" x2="150" y2="660"/>', '<line class="pl" x1="128" y1="514" x2="172" y2="514"/>')),
        group('p-ps', *trays),
    ])


def world_for(act, extra=()):
    return world(act, [shell(names=NAMES), desks(), cameras(), phone(), parts_layer(), *extra])


def build():
    lines = {'f3': 'کند', 'f2': 'قطعی', 'f1': 'خطای مبهم'}
    return {
        'file': 'infrastructure.html', 'hub': 'infrastructure',
        'title': 'زیرساخت فناوری سازمان: شبکه، سرور و برق | DaranX',
        'description': 'حوزهٔ زیرساخت DaranX: شبکه، دیتاسنتر و سرور، ذخیره‌سازی، برق و UPS و زیرساخت پسیو؛ بستری پایدار و مقیاس‌پذیر که با هم طراحی، اجرا و پشتیبانی می‌شود.',
        'keywords': 'زیرساخت فناوری اطلاعات, زیرساخت شبکه, دیتاسنتر, ذخیره‌سازی, UPS, زیرساخت پسیو, طراحی زیرساخت',
        'crumbs': [('راهکارها', 'solutions.html'), ('زیرساخت', 'infrastructure.html')],
        'badge': 'حوزهٔ زیرساخت',
        'h1': 'زیرساختی که سازمان <em>با خیال راحت روی آن می‌ایستد</em>',
        'verdict': 'شبکه، سرور، ذخیره‌سازی، برق و کابل‌کشی را متناسب با بار واقعی امروز و رشد فردا، با هم طراحی و اجرا می‌کنیم.',
        'cta2': ('اجزای زیرساخت', '#a2'),
        'service': {'name': 'طراحی و اجرای زیرساخت فناوری سازمان', 'type': 'IT infrastructure design and implementation',
                    'desc': 'بستری پایدار و مقیاس‌پذیر از شبکه، دیتاسنتر و سرور، ذخیره‌سازی، برق و UPS و زیرساخت پسیو.',
                    'parts': [(n, u) for n, u, _ in PARTS]},
        'acts': [
            {'id': 'a1', 'hero': True, 'len': 400, 'label': 'زیرساخت، بستر همهٔ فناوری سازمان', 'world': world_for('a1', [floor_warnings(lines)]),
             'caps': [(1, 'p', 'همه‌چیز روی یک بستر', 'از شبکه و سرور تا ذخیره‌سازی، برق و کابل‌کشی؛ همهٔ فناوری و عملیات سازمان روی زیرساخت قرار می‌گیرد.'),
                      (2, 'p', 'مشکل در یک نقطه نمی‌ماند', 'وقتی بستر درست ساخته نشده باشد، کندی، قطعی و خطاهای مبهم در جای‌جای سازمان ظاهر می‌شوند.')],
             'timeline': {'bounds': [.22, .6], 'stops': [0, .42, 1], 'cam': [WIDE]}},
            {'id': 'a2', 'len': 700, 'label': 'پنج جزء زیرساخت', 'world': world_for('a2'),
             'caps': [(0, 'lead', 'اجزای زیرساخت، هر کدام سر جای خودش', 'پنج بخش که با هم یک بستر منسجم می‌سازند؛ نه فهرستی از تجهیزات.')] +
                     [(k + 1, 'h3', n, d, link(n, u)) for k, (n, u, d) in enumerate(PARTS)],
             'timeline': {'bounds': [.14, .31, .48, .65, .82], 'stops': [.06, .22, .39, .56, .73, .92],
                          'cam': [WIDE, [.16, .26, {'z': 1.05, 'x': 560, 'y': 450}], [.33, .43, {'z': 1.5, 'x': 560, 'y': 735}],
                                  [.5, .6, {'z': 1.6, 'x': 640, 'y': 745}], [.67, .77, {'z': 1.15, 'x': 380, 'y': 600}], [.84, .94, WIDE]]}},
            {'id': 'a3', 'blueprint': True, 'len': 300, 'label': 'زیرساخت طراحی‌شده با هم', 'world': world_for('a3'),
             'caps': [(0, 'lead', 'با هم طراحی، نه کنار هم', 'هر جزء جای مشخص خود را دارد و برای یک هدف مشترک طراحی می‌شود؛ پایدار، مقیاس‌پذیر و ساده در نگهداری.'),
                      (1, 'h3', 'همه چیز سر جای درستش.', 'بیایید بستر درست سازمان شما را طراحی کنیم.', S.final_actions())],
             'timeline': {'bounds': [.6], 'stops': [.3, 1], 'cam': [WIDE]}},
        ],
        'states': [
            ('a1', ':is(.p-net,.p-dc,.p-st,.p-pw,.p-ps)', [1, 2], True, 'opacity:1'),
            ('a1', '.room:not(.room-b)', [2], False, 'fill:rgba(242,112,94,.10)'),
            ('a1', '.cab', [2], False, 'stroke:rgba(242,112,94,.6)'),
            ('a1', '.warn', [2], False, 'opacity:1'),
            ('a2', ':is(.p-net,.p-dc,.p-st,.p-pw,.p-ps)', [0], True, 'opacity:1'),
            ('a2', '.p-net', [1], False, 'opacity:1'),
            ('a2', '.p-net .cab', [1], False, 'stroke:var(--brand);stroke-dasharray:4 14;animation:flow 1.1s linear infinite'),
            ('a2', '.p-dc', [2], False, 'opacity:1'),
            ('a2', '.p-st', [3], False, 'opacity:1'),
            ('a2', '.p-pw', [4], False, 'opacity:1'),
            ('a2', '.p-pw .pw', [4], False, 'stroke:var(--brand);stroke-dasharray:6 12;animation:flow 1s linear infinite'),
            ('a2', '.p-ps', [5], False, 'opacity:1'),
            ('a2', '.tray', [5], False, 'fill:rgba(127,176,223,.35);stroke:var(--brand)'),
            ('a3', ':is(.p-net,.p-dc,.p-st,.p-pw,.p-ps)', [0, 1], True, 'opacity:1'),
            ('a3', '.p-net .cab', [0, 1], False, 'stroke:var(--brand);stroke-dasharray:4 14;animation:flow 1.4s linear infinite'),
        ],
        'css': """.p-net,.p-dc,.p-st,.p-pw,.p-ps{opacity:.12;transition:opacity .6s var(--ease-out)}
#a1:not([data-sc]) :is(.p-net,.p-dc,.p-st,.p-pw,.p-ps),#a1[data-sc="0"] :is(.p-net,.p-dc,.p-st,.p-pw,.p-ps){opacity:.5}
.tray{fill:rgba(127,176,223,.12);stroke:rgba(127,176,223,.4);stroke-width:1.5}
.pl{stroke:#6E89A6;stroke-width:6;stroke-linecap:round}
.cap-link{display:inline-flex;margin-top:.7rem;font-weight:800;color:var(--brand);border-bottom:1px solid rgba(127,176,223,.5)}""",
        'summary': {'h2': 'زیرساخت فناوری سازمان در DaranX',
                    'paras': ['حوزهٔ زیرساخت DaranX شامل شبکه، دیتاسنتر و سرور، ذخیره‌سازی، برق و UPS و زیرساخت پسیو است؛ بستری که همهٔ فناوری و عملیات سازمان روی آن قرار می‌گیرد و ما آن را متناسب با بار واقعی امروز و رشد فردا طراحی، اجرا و پشتیبانی می‌کنیم.',
                              'روش ما با اصل «طراحی مقدم بر اجراست» شروع می‌شود: پیش از انتخاب هر تجهیز، نیاز امروز، فشار رشد فردا و بخش‌های حساسی که نباید قطع شوند را می‌شناسیم. انتخاب تجهیزات نتیجهٔ طراحی است، نه نقطهٔ شروع آن.'],
                    'bullets': ['اجزای زیرساخت با هم و در ارتباط با یکدیگر طراحی می‌شوند، نه کنار هم.', 'اجرا با تجهیزات و روش مناسب و مستندسازی دقیق.',
                                'نگاه پشتیبانی از همان مرحلهٔ طراحی در نظر گرفته می‌شود.']},
        'parts': {'h2': 'اجزای زیرساخت، هر کدام سر جای خودش', 'items': PARTS},
        'why': ['پایداری و در دسترس‌بودن', 'مقیاس‌پذیری برای رشد', 'سادگی نگهداری و عیب‌یابی', 'کنترل هزینه در بلندمدت'],
        'method_h2': 'از شناخت تا پشتیبانی، در زیرساخت',
        'method': [('شناخت', 'بررسی بار فعلی، نقاط ضعف و اهداف رشد سازمان.'), ('طراحی', 'تعیین جایگاه و ارتباط هر جزء در یک بستر منسجم.'),
                   ('اجرا', 'پیاده‌سازی با تجهیزات و روش اجرایی مناسب.'), ('پشتیبانی', 'نگهداری و توسعهٔ پیوسته پس از تحویل.')],
        'faq': [('حوزهٔ زیرساخت DaranX شامل چه بخش‌هایی است؟', 'شبکه، دیتاسنتر و سرور، ذخیره‌سازی، برق و UPS و زیرساخت پسیو.'),
                ('آیا می‌شود فقط یک بخش را انجام داد؟', 'بله. هر بخش را می‌توان جداگانه طراحی و اجرا کرد، اما همیشه با نگاه به ارتباطش با بقیهٔ اجزای زیرساخت.'),
                ('زیرساخت موجود ما را هم بهبود می‌دهید؟', 'بله. وضعیت فعلی را ارزیابی می‌کنیم و نقاط ضعف را مرحله‌به‌مرحله و بدون توقف جریان کار برطرف می‌کنیم.'),
                ('پس از اجرا پشتیبانی دارید؟', 'بله. نگاه پشتیبانی از همان مرحلهٔ طراحی در نظر گرفته می‌شود و زیرساخت طوری ساخته می‌شود که نگهداری و توسعه‌اش ساده بماند.')],
        'related': {'h2': 'حوزه‌های دیگر', 'links': [('امنیت', 'security.html'), ('هوشمندی', 'intelligence.html'), ('همهٔ راهکارها', 'solutions.html')]},
        'cta': {'h2': 'بیایید بستر درست سازمان شما را طراحی کنیم.', 'p': 'یک گفتگوی کوتاه دربارهٔ وضعیت فعلی زیرساخت، اولین قدم است.'},
    }
