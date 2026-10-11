"""برق و UPS: تغذیهٔ پایدار (journey of power + before/after).
1 power reaches everything; one short outage stops everything
2 method (blueprint): what must stay on, UPS capacity, surge protection, generator, monitoring
3 the same outage: servers and network stay up on UPS, the generator takes over
"""
from kit import *
import layout as S

WIDE = {'z': 1, 'x': 540, 'y': 470}
NAMES = {'b': 'اتاق سرور و برق'}
PANEL = (380, 704)


def pole():
    return group('pole', '<line class="pl" x1="150" y1="500" x2="150" y2="660"/>', '<line class="pl" x1="128" y1="514" x2="172" y2="514"/>',
                 '<circle class="ins" cx="132" cy="508" r="4"/><circle class="ins" cx="168" cy="508" r="4"/>', label(150, 488, 'برق شهر', 'lbl sm'))


def panel():
    x, y = PANEL
    return group('pnl', f'<rect class="rk" x="{x}" y="{y}" width="62" height="86" rx="5"/>', use('lightning', x + 31, y + 40, 30))


def generator():
    return group('gen', '<rect class="rk" x="34" y="598" width="96" height="60" rx="8"/>', use('lightning', 82, 628, 28), label(82, 694, 'ژنراتور', 'lbl sm'))


def wires(with_ups=True):
    s = [path('M168 508 C190 540 196 600 196 640 V722 H380', 'wire pw pw-in'),
         path('M380 722 H226 V196', 'wire pw pw-up')]
    for fid, top, bot, _ in FLOORS:
        s.append(path(f'M226 {top + 46} H930', 'wire pw pw-floor'))
    if with_ups:
        s += [path('M442 748 H470', 'wire pw pw-crit'), path('M562 748 H620', 'wire pw pw-crit')]
    else:
        s.append(path('M442 748 H620', 'wire pw pw-crit'))
    return ''.join(s)


def building():
    return [shell(names=NAMES), desks(), aps(), switches(), cameras(), srack(620, cls='srack r1'), srack(710, cls='srack r2')]


def a1():
    return world('a1', building() + [wires(False), pole(), panel(), xmark(176, 560, 16), chip(300, 600, 'قطعی برق', 'chip alert cut', 'lightning-slash')])


def a2():
    surge_in = ' '.join(f'{250 + k * 15},{846 + (18 if k % 2 else -18)}' for k in range(15))
    return world('a2', building() + [
        wires(), pole(), panel(), ups(470, 708, 'ups u1', 'a2-ups'), generator(), path('M130 628 H186 V700', 'wire pw pw-gen'),
        asks([('f3', 'چه چیزی نباید خاموش شود؟'), ('f2', 'چند دقیقه یا چند ساعت؟'), ('f1', 'کدام بخش اولویت دارد؟'), ('b', 'ژنراتور داریم؟')], x=755),
        hot(('b',)),
        group('surge', f'<polyline class="sg-in" points="{surge_in}"/>', '<line class="sg-out" x1="576" y1="846" x2="800" y2="846"/>',
              label(360, 890, 'نوسان ورودی', 'lbl sm'), label(690, 890, 'برق پایدار', 'lbl sm')),
        group('genx', chip(300, 600, 'تا ورود ژنراتور', 'chip', 'clock')),
        group('watch', pulse_line(300, 846, 12, 'mon-p', 'pulse'), chip(640, 846, 'سلامت باتری', 'chip', 'battery-high')),
    ], )


def a3():
    return world('a3', building() + [
        wires(), pole(), panel(), ups(470, 708, 'ups u1', 'a3-ups'), generator(), path('M130 628 H186 V700', 'wire pw pw-gen'),
        xmark(176, 560, 16), chip(516, 842, 'سرور و شبکه روی UPS', 'chip ok-b', 'check-circle'),
        chip(300, 600, 'ژنراتور در مدار', 'chip gen-on', 'lightning'),
    ])


def build():
    return {
        'file': 'power-ups.html', 'hub': 'infrastructure',
        'title': 'برق و UPS سازمانی: تغذیهٔ پایدار | DaranX',
        'description': 'DaranX برق‌رسانی و سامانهٔ UPS سازمان را برای حفاظت در برابر قطعی و نوسان طراحی، اجرا و پشتیبانی می‌کند؛ با ظرفیت درست، هماهنگی با ژنراتور و پایش باتری.',
        'keywords': 'UPS سازمانی, طراحی برق اتاق سرور, حفاظت در برابر نوسان برق, ظرفیت UPS, باتری UPS, ژنراتور',
        'crumbs': [('راهکارها', 'solutions.html'), ('زیرساخت', 'infrastructure.html'), ('برق و UPS', 'power-ups.html')],
        'badge': 'زیرساخت · برق و UPS',
        'h1': 'تغذیهٔ پایدار، <em>پیش‌نیاز در دسترس بودن</em>',
        'verdict': 'برق‌رسانی و UPS سازمان را طوری طراحی می‌کنیم که قطعی یا نوسان برق، سرویس‌ها را متوقف نکند و تجهیزات حساس آسیب نبینند.',
        'cta2': ('قطعی برق چه می‌کند؟', '#a2'),
        'service': {'name': 'طراحی برق‌رسانی و سامانهٔ UPS', 'type': 'Power distribution and UPS design',
                    'desc': 'طراحی برق‌رسانی و سامانهٔ UPS برای حفاظت در برابر قطعی و نوسان، تا سرویس‌ها بی‌وقفه بمانند.'},
        'acts': [
            {'id': 'a1', 'hero': True, 'len': 400, 'label': 'برق، پیش‌نیاز همهٔ سرویس‌ها', 'world': a1(),
             'caps': [(1, 'p', 'برق، پیش‌نیاز خاموش همه‌چیز', 'سرور، شبکه، دوربین و هر سیستم دیگری فقط وقتی پایدار است که برقش پایدار باشد.'),
                      (2, 'p', 'یک قطعی کوتاه، همه‌چیز متوقف', 'یک قطعی کوتاه یا نوسان ناگهانی سرویس‌ها را متوقف می‌کند و حتی تجهیزات حساس را از کار می‌اندازد.')],
             'timeline': {'bounds': [.22, .6], 'stops': [0, .42, 1],
                          'cam': [WIDE, [.14, .38, {'z': 1.2, 'x': 440, 'y': 600}], [.62, .86, WIDE]]}},
            {'id': 'a2', 'blueprint': True, 'len': 600, 'label': 'روش DaranX در طراحی برق و UPS', 'world': a2(),
             'caps': [(0, 'lead', 'اول نیاز واقعی را می‌شناسیم', 'چه تجهیزاتی نباید خاموش شوند؟ چند دقیقه یا چند ساعت باید روشن بمانند؟ کدام بخش اولویت دارد؟'),
                      (1, 'h3', 'UPS با ظرفیت و زمان درست', 'اندازه، ظرفیت و زمان پشتیبانی UPS از همین پاسخ‌ها به دست می‌آید.'),
                      (2, 'h3', 'حفاظت در برابر نوسان', 'نوسان و اضافه‌ولتاژ پیش از رسیدن به تجهیزات حساس مهار می‌شود.'),
                      (3, 'h3', 'هماهنگ با ژنراتور', 'اگر ژنراتور دارید، UPS تا ورود آن به مدار، سرویس‌ها را روشن نگه می‌دارد.'),
                      (4, 'h3', 'پایش برق و سلامت باتری', 'وضعیت برق پیوسته پایش می‌شود و باتری‌ها در زمان درست بررسی و تعویض می‌شوند.')],
             'timeline': {'bounds': [.22, .4, .58, .76], 'stops': [.1, .31, .49, .67, .9],
                          'cam': [WIDE, [.24, .36, {'z': 1.4, 'x': 500, 'y': 760}], [.6, .7, {'z': 1.25, 'x': 400, 'y': 720}],
                                  [.78, .86, {'z': 1.4, 'x': 500, 'y': 760}]],
                          'vars': [['a2-ups', 't', .26, .38]]}},
            {'id': 'a3', 'blueprint': True, 'len': 420, 'label': 'قطعی برق در سازمانی که آماده است', 'world': a3(),
             'caps': [(0, 'lead', 'قطعی، این بار بی‌سروصدا', 'برق شهر قطع شده، اما سرور و شبکه روی UPS روشن مانده‌اند و کار طبق نقشه ادامه دارد.'),
                      (1, 'h3', 'ژنراتور وارد مدار می‌شود', 'ژنراتور بار را برمی‌دارد و قطعی برق به یک بحران تبدیل نمی‌شود.'),
                      (2, 'h3', 'همه چیز سر جای درستش.', 'برق و UPS را برای مسئلهٔ واقعی شما طراحی کنیم.', S.final_actions())],
             'timeline': {'bounds': [.45, .75], 'stops': [.3, .6, 1],
                          'cam': [{'z': 1.05, 'x': 470, 'y': 600}, [.46, .58, {'z': 1.2, 'x': 400, 'y': 680}], [.78, .9, WIDE]],
                          'vars': [['a3-ups', 'd', .06, .3], ['a3-ups', 'r', .5, .7]]}},
        ],
        'states': [
            ('a1', '.pw', [1], True, 'stroke:var(--brand);stroke-dasharray:6 12;animation:flow 1s linear infinite'),
            ('a1', '.mon', [1], True, 'fill:#2A5C8F;stroke:var(--brand)'),
            ('a1', ':is(.brk,.cut)', [2], True, 'opacity:1'),
            ('a1', '.pw', [2], False, 'opacity:.25'),
            ('a1', ':is(.mon,.srack .unit)', [2], False, 'fill:#0A1828;stroke:#1C3048'),
            ('a1', '.led', [2], False, 'opacity:.1;animation:none'),
            ('a1', '.room', [2], False, 'fill:rgba(0,0,0,.25)'),
            ('a2', ':is(.ask,.hot,.hot-t)', [0], True, 'opacity:1'),
            ('a2', '.ups', [1, 2, 3, 4], True, 'opacity:1'),
            ('a2', '.pw-crit', [1, 2, 3, 4], True, 'stroke:var(--brand);stroke-width:5'),
            ('a2', '.surge', [2], True, 'opacity:1'),
            ('a2', ':is(.gen,.pw-gen,.genx)', [3, 4], True, 'opacity:1'),
            ('a2', '.watch', [4], True, 'opacity:1'),
            ('a3', '.brk', [0, 1, 2], True, 'opacity:1'),
            ('a3', ':is(.pw-in,.pw-floor,.pw-up)', [0], True, 'opacity:.2'),
            ('a3', '.mon', [0], True, 'fill:#0A1828;stroke:#1C3048'),
            ('a3', '.pw-crit', [0, 1, 2], True, 'stroke:var(--brand);stroke-width:5;stroke-dasharray:6 12;animation:flow 1s linear infinite'),
            ('a3', '.ok-b', [0], True, 'opacity:1'),
            ('a3', ':is(.gen-on)', [1], True, 'opacity:1'),
            ('a3', '.pw-gen', [1, 2], True, 'stroke:var(--brand);stroke-dasharray:6 12;animation:flow 1s linear infinite'),
            ('a3', '.gen .ic', [1, 2], True, 'fill:var(--brand)'),
        ],
        'css': """.pl{stroke:#6E89A6;stroke-width:6;stroke-linecap:round}.ins{fill:#AEC4DB}
.pw{stroke-width:4}.pw-in{stroke-width:6}
.pnl .rk{fill:#0B1D33;stroke:#3A6189;stroke-width:2}.pnl .ic{fill:#AEC4DB}
.gen .rk{fill:#0B1D33;stroke:#3A6189;stroke-width:2}.gen .ic{fill:#7F97B0}
.ups,.gen,.pw-gen,.surge,.watch,.genx,.cut,.ok-b,.gen-on{opacity:0;transition:opacity .5s var(--ease-out)}
#a3 .ups,#a3 .gen,#a3 .pw-gen{opacity:1}
.sg-in{fill:none;stroke:var(--alert);stroke-width:3;stroke-linejoin:round}.sg-out{stroke:var(--brand);stroke-width:4;stroke-linecap:round}
#a2-ups .bat-in{transform:scaleX(var(--t,1))}
#a3-ups .bat-in{transform:scaleX(calc(1 - (var(--d,0) - var(--r,0)) * .55))}
.mon{transition:fill .4s ease,stroke .4s ease}
#a1 .mon{transition-delay:calc(var(--k,0) * 40ms)}""",
        'summary': {'h2': 'طراحی برق‌رسانی و UPS سازمانی در DaranX',
                    'paras': ['DaranX برق‌رسانی و سامانهٔ UPS سازمان را برای حفاظت در برابر قطعی و نوسان برق طراحی، اجرا و پشتیبانی می‌کند تا سرویس‌ها بی‌وقفه بمانند و تجهیزات حساس آسیب نبینند.',
                              'برق پیش‌نیاز همهٔ سرور‌ها، شبکه و دوربین‌هاست. یک قطعی کوتاه یا نوسان ناگهانی می‌تواند سرویس‌ها را متوقف کند؛ برای همین تغذیه و حفاظت برق از همان مرحلهٔ طراحی و به‌عنوان بخشی از پایداری کل راهکار دیده می‌شود.'],
                    'bullets': ['شناخت تجهیزاتی که باید بی‌وقفه بمانند و زمان لازم برای روشن ماندن آن‌ها.', 'تعیین ظرفیت، زمان پشتیبانی و معماری UPS و مسیر برق‌رسانی.',
                                'حفاظت در برابر نوسان و اضافه‌ولتاژ.', 'هماهنگی با ژنراتور در صورت وجود.', 'پایش پیوستهٔ وضعیت برق، و بررسی و تعویض به‌موقع باتری‌ها.']},
        'why': ['تداوم سرویس در قطعی برق', 'حفاظت تجهیزات حساس', 'کاهش ریسک خرابی و توقف'],
        'method': [('شناخت', 'تجهیزات حیاتی، زمان لازم و اولویت هر بخش را مشخص می‌کنیم.'), ('طراحی', 'ظرفیت UPS، مسیر برق، حفاظت از نوسان و هماهنگی با ژنراتور.'),
                   ('اجرا', 'نصب منظم و ایمن برق‌رسانی و UPS.'), ('پشتیبانی', 'پایش وضعیت برق و بررسی و تعویض به‌موقع باتری‌ها.')],
        'faq': [('ظرفیت UPS را چطور تعیین می‌کنید؟', 'از نیاز واقعی: چه تجهیزاتی باید بی‌وقفه بمانند، در قطعی برق چند دقیقه یا چند ساعت باید روشن بمانند تا به‌سلامت خاموش شوند یا ژنراتور وارد مدار شود، و کدام بخش‌ها اولویت تغذیه دارند.'),
                ('UPS در برابر نوسان برق هم محافظت می‌کند؟', 'حفاظت در برابر نوسان و اضافه‌ولتاژ بخشی از طراحی است تا تجهیزات حساس آسیب نبینند.'),
                ('با ژنراتور موجود هماهنگ می‌شود؟', 'بله. اگر ژنراتور دارید، هماهنگی UPS با آن در طراحی دیده می‌شود.'),
                ('باتری‌های UPS نگهداری لازم دارند؟', 'بله. باتری‌ها عمر دارند و باید در زمان درست بررسی و تعویض شوند؛ پایش وضعیت برق و پشتیبانی را از ابتدا لحاظ می‌کنیم.')],
        'related': {'h2': 'بقیهٔ حوزهٔ زیرساخت', 'links': [('شبکه', 'network.html'), ('دیتاسنتر و سرور', 'datacenter-server.html'), ('ذخیره‌سازی', 'storage.html'),
                                                          ('زیرساخت پسیو', 'passive.html'), ('همهٔ حوزهٔ زیرساخت', 'infrastructure.html')]},
        'cta': {'h2': 'برق و UPS را برای مسئلهٔ واقعی شما طراحی کنیم.', 'p': 'اول نیاز شما را مشخص می‌کنیم، بعد می‌گوییم چه چیزی واقعاً لازم دارید.'},
    }
