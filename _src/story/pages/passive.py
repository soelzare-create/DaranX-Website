"""زیرساخت پسیو: بستر فیزیکی منظم (before/after).
1 the silent base under every layer; messy and undocumented, every change becomes a hunt
2 method (blueprint): points today and tomorrow, structured cabling, copper or fibre, labels, tidy rack and tested paths
3 result: a fault found in minutes; growth plugs into reserved capacity
"""
from kit import *
import layout as S

WIDE = {'z': 1, 'x': 540, 'y': 470}
RACKV = {'z': 1.6, 'x': 700, 'y': 650}


def base(tangled=False, lanes=False):
    cab = tangle(seed=11) if tangled else neat_cables(lanes=lanes)
    return [shell(), group('L-cab', cab), desks(), aps(), switches(), cameras(), phone(), rack(), server_icon(), cloud()]


def trays():
    """cable trays along every ceiling and the riser: the passive layer itself"""
    s = [f'<rect class="tray" x="{X0 + 20}" y="{fb(f) - 14}" width="{RISER - X0 - 20}" height="10" rx="3"/>' for f, _, _, _ in FLOORS]
    s.append(f'<rect class="tray" x="{RISER - 8}" y="{fb("f3") - 40}" width="16" height="{CORE_Y - fb("f3") + 50}" rx="3"/>')
    return group('trays', *s)


def layers():
    return group('layers', chip(600, 210, 'شبکه', 'chip lay'), chip(600, 380, 'سرور', 'chip lay'), chip(600, 550, 'امنیت', 'chip lay'),
                 chip(600, 842, 'پسیو: کابل، رک و مسیرها', 'chip lay base-l', 'tree-structure'))


def a1():
    return world('a1', base() + [trays(), layers()])


def a2():
    return world('a2', base(tangled=True) + [node(queue=False), uplink(),
        f'<g class="hunt" id="a2-hunt" transform="translate(640 420)">{use("magnifying-glass", 0, 0, 74)}</g>',
        chip(600, 842, 'ساعت‌ها عیب‌یابی برای یک تغییر کوچک', 'chip alert', 'clock')])


def tags():
    s, n = [], 0
    for fid, top, bot, _ in FLOORS:
        for i, x in enumerate(DESKS[fid]):
            n += 1
            s.append(group(f'tagc t{n:02d}', f'<rect x="{x + 47}" y="{bot - 30}" width="30" height="16" rx="3"/>', label(x + 62, bot - 18, f'{n:02d}', 'lbl tag-t')))
    return ''.join(s)


def spare():
    """reserved capacity: dashed drops and space in the trays for tomorrow"""
    return group('spare', *[f'<path class="cab spare-c" d="M{x} {fb(f) - 39} V{fb(f) - 8}"/>' for f, x in [('f1', 290), ('f1', 730), ('f1', 850)]],
                 chip(560, 642, 'ظرفیت رزرو برای فردا', 'chip', 'stack'))


def a3():
    return world('a3', base(lanes=True) + [
        trays(),
        asks([('f3', 'امروز چند نقطه؟'), ('f2', 'چه سرویس‌هایی؟'), ('f1', 'فردا چه اضافه می‌شود؟')], x=720),
        spare(),
        group('media', chip(860, 470, 'فیبر', 'chip', 'lightning'), chip(420, 470, 'مسی', 'chip', 'plug')),
        tags(), group('docb', chip(700, 842, 'نقشه و مستند هر مسیر و پورت', 'chip', 'clipboard-text')),
        group('tested', *[use('check-circle', x, y, 22) for x, y in [(870, 700), (870, 728), (870, 760), (940, 300), (940, 470), (940, 640)]]),
    ])


def a4():
    px, py = desk_port('f2', 1)
    d = f'M{px} {py} V{fb("f2") - 8} H{RISER - 18} V{fb("f2") - 34} H{RISER} V{CORE_Y + 8} H{RACK[0] + RACK[2] - 8}'
    return world('a4', base(lanes=True) + [
        trays(), tags(),
        f'<path class="trace" id="a4-trace" pathLength="1" d="{d}"/>',
        chip(440, 300, 'پورت ۰۷: پیدا شد', 'chip ok-b', 'check-circle'),
        new_floor(),
    ])


def build():
    return {
        'file': 'passive.html', 'hub': 'infrastructure',
        'title': 'زیرساخت پسیو و کابل‌کشی ساخت‌یافته | DaranX',
        'description': 'DaranX زیرساخت پسیو سازمان را با کابل‌کشی ساخت‌یافته، رک منظم، برچسب‌گذاری و مستندسازی دقیق طراحی و اجرا می‌کند تا عیب‌یابی و توسعه ساده بماند.',
        'keywords': 'زیرساخت پسیو, کابل‌کشی ساخت‌یافته, رک شبکه, فیبر نوری, برچسب‌گذاری کابل, مستندسازی شبکه',
        'crumbs': [('راهکارها', 'solutions.html'), ('زیرساخت', 'infrastructure.html'), ('زیرساخت پسیو', 'passive.html')],
        'badge': 'زیرساخت · پسیو',
        'h1': 'بستر فیزیکی منظم که <em>نگهداری را ساده می‌کند</em>',
        'verdict': 'کابل‌کشی، رک و مسیرهای فیزیکی را استاندارد و مستند طراحی و اجرا می‌کنیم؛ پایهٔ آرام همهٔ لایه‌های بالاتر.',
        'cta2': ('پسیو نامرتب چه می‌کند؟', '#a2'),
        'service': {'name': 'طراحی و اجرای زیرساخت پسیو', 'type': 'Structured cabling and passive infrastructure',
                    'desc': 'کابل‌کشی، رک و مسیرهای استاندارد و مستند؛ چیدمانی که عیب‌یابی و توسعه را ساده و کم‌هزینه می‌کند.'},
        'acts': [
            {'id': 'a1', 'hero': True, 'len': 300, 'label': 'زیرساخت پسیو، پایهٔ همهٔ لایه‌ها', 'world': a1(),
             'caps': [(1, 'p', 'پایهٔ خاموش همهٔ لایه‌ها', 'شبکه، سرور و امنیت روی کابل‌کشی، رک و مسیرهای فیزیکی می‌نشینند؛ بخشی که کمتر دیده می‌شود.')],
             'timeline': {'bounds': [.3], 'stops': [0, 1], 'cam': [WIDE, [.3, .7, {'z': .95, 'x': 560, 'y': 520}]]}},
            {'id': 'a2', 'len': 300, 'label': 'پسیو نامرتب و بدون مستند', 'world': a2(),
             'caps': [(0, 'lead', 'نامرتب و بدون مستند', 'هر تغییر کوچک به یک ماجرا تبدیل می‌شود و عیب‌یابی ساعت‌ها زمان می‌برد.')],
             'timeline': {'bounds': [], 'stops': [.5],
                          'cam': [WIDE, [.1, .4, {'z': 1.15, 'x': 640, 'y': 500}]],
                          'draws': [['w-a2', 0, .3]],
                          'moves': [['a2-hunt', [[420, 252], [760, 418], [330, 598], [880, 600], [640, 742]], .3, 1]]}},
            {'id': 'a3', 'blueprint': True, 'len': 600, 'label': 'روش DaranX در طراحی زیرساخت پسیو', 'world': a3(),
             'caps': [(0, 'lead', 'امروز چند نقطه، فردا چند؟', 'مسیرها و ظرفیت طوری دیده می‌شوند که توسعهٔ فردا بدون خراب‌کاری روی کار امروز ممکن باشد.'),
                      (1, 'h3', 'کابل‌کشی ساخت‌یافته و استاندارد', 'هر مسیر جای مشخص دارد و از سینی و مسیر تمیز عبور می‌کند.'),
                      (2, 'h3', 'مسی یا فیبر، متناسب با نیاز', 'بستر هر مسیر بر اساس نیاز انتخاب می‌شود، نه عادت.'),
                      (3, 'h3', 'برچسب و مستند برای هر پورت', 'هر مسیر و پورت برچسب و سابقه دارد؛ نقشهٔ شبکه همیشه در دسترس است.'),
                      (4, 'h3', 'رک منظم، مسیرهای تست‌شده', 'مسیرها تست و همه‌چیز مستند تحویل داده می‌شود.')],
             'timeline': {'bounds': [.22, .4, .58, .76], 'stops': [.1, .31, .49, .67, .9],
                          'cam': [WIDE, [.76, .86, RACKV]]}},
            {'id': 'a4', 'blueprint': True, 'len': 420, 'label': 'عیب‌یابی و توسعه روی پسیو منظم', 'world': a4(),
             'caps': [(0, 'lead', 'عیب‌یابی در چند دقیقه', 'برچسب و مستند، مسیر خراب را مستقیم نشان می‌دهند؛ ساعت‌ها جست‌وجو به چند دقیقه می‌رسد.'),
                      (1, 'h3', 'توسعه بدون باز کردن کل کار', 'طبقهٔ جدید به ظرفیت رزرو‌شده وصل می‌شود، بدون دست بردن در کار امروز.'),
                      (2, 'h3', 'همه چیز سر جای درستش.', 'زیرساخت پسیو را برای مسئلهٔ واقعی شما طراحی کنیم.', S.final_actions())],
             'timeline': {'bounds': [.45, .75], 'stops': [.3, .6, 1],
                          'cam': [WIDE, [.45, .58, {'z': .84, 'x': 540, 'y': 390}]],
                          'draws': [['a4-trace', .04, .26]]}},
        ],
        'states': [
            ('a1', ':is(.trays,.layers)', [1], True, 'opacity:1'),
            ('a1', '.tray', [1], True, 'fill:rgba(127,176,223,.35);stroke:var(--brand)'),
            ('a2', '.tg', [0], True, 'stroke:rgba(242,112,94,.45)'),
            ('a2', ':is(.hunt,.chip)', [0], True, 'opacity:1'),
            ('a3', '.L-cab', [0], False, 'opacity:.12'),
            ('a3', ':is(.ask,.spare)', [0], True, 'opacity:1'),
            ('a3', '.trays', [1, 2, 3, 4], True, 'opacity:1'),
            ('a3', '.media', [2], True, 'opacity:1'),
            ('a3', '.riser', [2], True, 'stroke:#C9DEF2;stroke-width:3;stroke-dasharray:none;filter:drop-shadow(0 0 6px rgba(127,176,223,.8))'),
            ('a3', ':is(.tagc,.docb)', [3, 4], True, 'opacity:1'),
            ('a3', '.tested', [4], True, 'opacity:1'),
            ('a4', ':is(.trays,.tagc)', [0, 1, 2], True, 'opacity:1'),
            ('a4', ':is(.trace,.ok-b)', [0], True, 'opacity:1'),
            ('a4', '.t07 rect', [0], True, 'fill:var(--brand)'),
            ('a4', '.newf', [1, 2], True, 'opacity:1;transform:none'),
        ],
        'css': """.trays,.layers,.spare,.media,.docb,.tested,.trace,.ok-b,.world .chip.alert,#a2 .hunt{opacity:0;transition:opacity .5s var(--ease-out)}
.tray{fill:rgba(127,176,223,.12);stroke:rgba(127,176,223,.4);stroke-width:1.5;transition:fill .5s ease}
.lay rect{stroke:var(--brand)}.base-l rect{fill:rgba(127,176,223,.18)}
.spare-c{stroke:var(--brand);stroke-dasharray:4 6;opacity:.8}
.tested .ic{fill:#7FD1A8}
.trace{fill:none;stroke:#FFFFFF;stroke-width:6;stroke-linecap:round;stroke-dasharray:1;stroke-dashoffset:var(--draw,0);filter:drop-shadow(0 0 6px rgba(127,176,223,.9))}""",
        'summary': {'h2': 'زیرساخت پسیو و کابل‌کشی ساخت‌یافته در DaranX',
                    'paras': ['DaranX زیرساخت پسیو سازمان، یعنی کابل‌کشی، رک و مسیرهای فیزیکی، را استاندارد و مستند طراحی و اجرا می‌کند تا عیب‌یابی، نگهداری و توسعه در تمام عمر زیرساخت ساده بماند.',
                              'پسیو پایهٔ خاموشی است که شبکه، سرور و امنیت روی آن می‌نشینند. وقتی نامرتب و بدون مستندات باشد، هر تغییر کوچک به یک ماجرا تبدیل می‌شود و عیب‌یابی ساعت‌ها زمان می‌برد.'],
                    'bullets': ['شناخت نقاط و سرویس‌های امروز و آنچه فردا اضافه می‌شود.', 'کابل‌کشی ساخت‌یافته و استاندارد.', 'انتخاب درست بستر، مسی یا فیبر، متناسب با نیاز.',
                                'برچسب‌گذاری و مستندسازی دقیق هر مسیر و پورت.', 'چیدمان منظم رک، تست مسیرها و تحویل مستند.']},
        'why': ['عیب‌یابی سریع و کم‌دردسر', 'توسعهٔ آسان در آینده', 'دوام و نظم بلندمدت'],
        'method': [('شناخت', 'نقاط و سرویس‌های امروز و فردا را می‌شناسیم.'), ('طراحی', 'مسیرها، ظرفیت، بستر مسی یا فیبر و چیدمان رک.'),
                   ('اجرا', 'اجرای استاندارد، تست مسیرها و برچسب‌گذاری.'), ('پشتیبانی', 'مستندات کامل و نگهداری در طول عمر زیرساخت.')],
        'faq': [('کابل‌کشی ساخت‌یافته چه فایده‌ای دارد؟', 'نظمی که عیب‌یابی را از چند ساعت به چند دقیقه می‌رساند و از خطاهای انسانی جلوگیری می‌کند.'),
                ('بین کابل مسی و فیبر چطور انتخاب می‌کنید؟', 'بستر هر مسیر متناسب با نیاز آن مسیر انتخاب می‌شود.'),
                ('برای توسعهٔ آینده چه پیش‌بینی می‌کنید؟', 'مسیرها و ظرفیت را طوری طراحی می‌کنیم که افزودن یک بخش جدید نیازمند باز کردن کل کار نباشد.'),
                ('در پایان کار چه تحویل می‌دهید؟', 'مسیرهای تست‌شده و مستندات کامل، شامل برچسب و سابقهٔ هر مسیر و پورت.')],
        'related': {'h2': 'بقیهٔ حوزهٔ زیرساخت', 'links': [('شبکه', 'network.html'), ('دیتاسنتر و سرور', 'datacenter-server.html'), ('ذخیره‌سازی', 'storage.html'),
                                                          ('برق و UPS', 'power-ups.html'), ('همهٔ حوزهٔ زیرساخت', 'infrastructure.html')]},
        'cta': {'h2': 'زیرساخت پسیو را برای مسئلهٔ واقعی شما طراحی کنیم.', 'p': 'اول نیاز شما را مشخص می‌کنیم، بعد می‌گوییم چه چیزی واقعاً لازم دارید.'},
    }
