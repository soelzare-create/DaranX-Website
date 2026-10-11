"""شبکه: سفر یک کلیک (journey + before/after), approved storyboard."""
from kit import *
import layout as S

WIDE = {'z': 1, 'x': 540, 'y': 470}


def hero_fx():
    fid, i = 'f3', 1
    px, py = desk_port(fid, i)
    x = DESKS[fid][i]
    d = (f'M{px} {py} V{fb(fid) - 8} H{RISER - 18} V{fb(fid) - 34} H{RISER} V{CORE_Y + 8} H{RACK[0] + RACK[2] - 8}'
         f' V{ROUTER_Y + 8} H{160} V{CLOUD[1] + 40}')
    return ''.join([
        f'<circle class="click-ring" cx="{x + 40}" cy="{fb(fid) - 69}" r="26"/>',
        group('svc', use('file-text', x + 40, fb(fid) - 112, 30), use('envelope-simple', x - 2, fb(fid) - 104, 26)),
        mover('a1-pk', d, 'a1-dot'),
        *[group('svc2', use(ic, ix, iy, 24)) for ic, ix, iy in [('envelope-simple', 650, 218), ('users-three', 530, 388), ('file-text', 650, 558),
                                                                   ('video-camera', CAMS[1], 562), ('phone', 410, 556)]],
    ])


def problem_fx():
    return ''.join([
        floor_warnings({'f3': 'کند', 'f2': 'قطعی', 'f1': 'خطای مبهم'}),
        f'<g class="hunt" id="a2-hunt" transform="translate(700 418)">{use("magnifying-glass", 0, 0, 74)}</g>',
        *[f'<g class="unk" style="--k:{k}"><circle cx="{qx}" cy="{qy}" r="15"/>{label(qx, qy + 7, "؟", "lbl q-t")}</g>'
          for k, (qx, qy) in enumerate([(420, 270), (700, 430), (330, 610), (860, 600), (640, 760)])],
    ])


def plan_fx():
    s = [asks([('f3', 'چند کاربر؟'), ('f2', 'چه سرویس‌هایی؟'), ('f1', 'بار امروز؟'), ('b', 'رشد تا سه سال؟')]), hot(('f3', 'b'))]
    for fid, name in [('f3', 'مالی'), ('f2', 'فروش'), ('f1', 'دوربین‌ها')]:
        s.append(chip(X1 - 148, ft(fid) + 68, name, f'chip lane-t lane-t-{fid}'))
    s.append(chip(AP_X + 85, TOP + 72, 'Wi-Fi', 'chip lane-t'))
    for fid, top, bot, _ in FLOORS:
        s.append(f'<ellipse class="wifi" cx="{AP_X + 20}" cy="{(top + bot) / 2 + 6}" rx="330" ry="{(bot - top) / 2 - 12}"/>')
    n = 0
    for fid, top, bot, _ in FLOORS:
        for i, x in enumerate(DESKS[fid]):
            n += 1
            s.append(group('tagc', f'<rect x="{x + 47}" y="{bot - 30}" width="30" height="16" rx="3"/>', label(x + 62, bot - 18, f'{n:02d}', 'lbl tag-t')))
    s.append(shield_box())
    s.append(pulse_line())
    return ''.join(s)


def test_fx():
    fid, i = 'f3', 1
    px, py = desk_port(fid, i)
    x, y = sw_xy(fid)
    d = (f'M{px} {py} V{fb(fid) - 8} H{RISER - 18} V{fb(fid) - 34} H{x} V{y + 8} H{x - 12} V{ft(fid) + 44} H{RISER2}'
         f' V{CORE2_Y + 8} H{RACK[0] + 8}')
    return ''.join([
        group('brk', f'<line x1="{RISER - 18}" y1="560" x2="{RISER + 18}" y2="596"/><line x1="{RISER + 18}" y1="560" x2="{RISER - 18}" y2="596"/>'),
        mover('a4-pk', d, 'a4-dot'),
        chip(705, ft('f2') + 23, 'کار ادامه دارد', 'chip ok-b', 'check-circle'),
        new_floor(),
    ])


def build():
    worlds = {
        'a1': world('a1', [shell(), neat_cables(), furniture(), hero_fx()]),
        'a2': world('a2', [shell(), tangle(), uplink(), node(), furniture(), problem_fx()]),
        'a3': world('a3', [shell(), group('L-alt', second_path()), group('L-cab', neat_cables(lanes=True)), furniture(), plan_fx()]),
        'a4': world('a4', [shell(), group('L-alt', second_path()), group('L-cab', neat_cables(lanes=True)), furniture(), plan_fx(), test_fx()]),
    }
    return {
        'file': 'network.html', 'hub': 'infrastructure',
        'title': 'طراحی و اجرای شبکهٔ سازمانی | DaranX',
        'description': 'DaranX شبکهٔ باسیم و بی‌سیم سازمان را برای بار واقعی امروز و رشد فردا طراحی، اجرا و پشتیبانی می‌کند؛ با جداسازی بخش‌ها، مسیر جایگزین و مستندات کامل.',
        'keywords': 'طراحی شبکه سازمانی, اجرای شبکه, شبکه باسیم و بی‌سیم, VLAN, افزونگی شبکه, کابل‌کشی شبکه, پشتیبانی شبکه',
        'crumbs': [('راهکارها', 'solutions.html'), ('زیرساخت', 'infrastructure.html'), ('شبکه', 'network.html')],
        'badge': 'زیرساخت · شبکه',
        'h1': 'بستر ارتباطی پایدار برای <em>همهٔ سرویس‌ها</em>',
        'verdict': 'شبکهٔ باسیم و بی‌سیم سازمان را برای بار واقعی امروز و رشد فردا طراحی، اجرا و پشتیبانی می‌کنیم.',
        'cta2': ('چرا شبکه مهم است؟', '#a2'),
        'service': {'name': 'طراحی، اجرا و پشتیبانی شبکهٔ سازمانی', 'type': 'Enterprise network design and implementation',
                    'desc': 'طراحی و پیاده‌سازی شبکهٔ باسیم و بی‌سیم، از دسترسی کاربران تا ارتباط دیتاسنتر، با نگاه به امنیت و پایش از همان ابتدا.'},
        'acts': [
            {'id': 'a1', 'hero': True, 'len': 400, 'label': 'سفر یک کلیک در شبکهٔ سازمان', 'world': worlds['a1'],
             'caps': [(1, 'p', 'همه‌چیز از یک کلیک شروع می‌شود', 'فاکتور، ایمیل، دوربین، تلفن؛ هر کاری در سازمان از شبکه رد می‌شود.'),
                      (2, 'p', 'همهٔ سرویس‌ها روی یک بستر', 'شبکه همان بستری است که سرویس‌های سازمان روی آن نفس می‌کشند.')],
             'timeline': {'bounds': [.22, .6], 'stops': [0, .42, 1],
                          'cam': [WIDE, [.14, .38, {'z': 1.9, 'x': 430, 'y': 270}], [.62, .86, WIDE]],
                          'dots': [['a1-pk', 'a1-dot', .62, .97]]}},
            {'id': 'a2', 'len': 420, 'label': 'شبکه‌ای که طراحی نشده', 'world': worlds['a2'],
             'caps': [(0, 'lead', 'شبکه‌ای که طراحی نشده', 'تجهیزات بی‌نقشه اضافه شده‌اند و همهٔ کابل‌ها از یک گره رد می‌شوند.'),
                      (1, 'h3', 'مشکل همان‌جا نمی‌ماند', 'کندی، قطعی پراکنده و خطاهای مبهم در جای‌جای سازمان پیدا می‌شود.'),
                      (2, 'h3', 'عیب‌یابی سخت و پرهزینه', 'بدون مستندات، پیدا کردن ریشهٔ مشکل یعنی گشتن کل ساختمان.')],
             'timeline': {'bounds': [.45, .75], 'stops': [.3, .6, 1],
                          'cam': [WIDE, [.04, .28, {'z': 1.45, 'x': 820, 'y': 455}], [.45, .6, WIDE]],
                          'draws': [['w-a2', 0, .26]],
                          'moves': [['a2-hunt', [[420, 252], [700, 418], [330, 598], [860, 590], [640, 742]], .76, .98]]}},
            {'id': 'a3', 'blueprint': True, 'len': 600, 'label': 'روش DaranX در طراحی شبکه', 'world': worlds['a3'],
             'caps': [(0, 'lead', 'اول مسئله را می‌فهمیم', 'چند کاربر، چه سرویس‌هایی، بار امروز و رشد یک تا سه سال آینده؟ کدام بخش‌ها نباید قطع شوند؟'),
                      (1, 'h3', 'هر بخش، مسیر خودش', 'جداسازی منطقی بخش‌ها (VLAN) برای نظم و امنیت؛ ترافیک هر بخش در مسیر خودش.'),
                      (2, 'h3', 'مسیر دوم برای بخش‌های حیاتی', 'افزونگی، تا یک خرابی کل سازمان را زمین نزند؛ و پوشش بی‌سیم یکنواخت در همهٔ طبقه‌ها.'),
                      (3, 'h3', 'کابل‌کشی منظم، مستند کامل', 'هر کابل برچسب و جای مشخص دارد؛ شبکه امروز کار می‌کند و فردا قابل نگهداری است.'),
                      (4, 'h3', 'آماده برای امنیت و پایش', 'امنیت و پایش از روز اول در طراحی هستند، تا بعداً به بازطراحی پرهزینه نیاز نباشد.')],
             'timeline': {'bounds': [.22, .4, .58, .76], 'stops': [.1, .31, .49, .67, .9],
                          'cam': [WIDE, [.58, .66, {'z': 1.6, 'x': 680, 'y': 640}], [.76, .84, WIDE]]}},
            {'id': 'a4', 'blueprint': True, 'len': 420, 'label': 'همان خرابی در شبکهٔ طراحی‌شده', 'world': worlds['a4'],
             'caps': [(0, 'lead', 'همان خرابی، این بار بی‌اثر', 'مسیر اصلی قطع شده، اما ترافیک از مسیر دوم می‌رود و کار سازمان ادامه پیدا می‌کند.'),
                      (1, 'h3', 'رشد بدون بازطراحی', 'طبقه یا شعبهٔ جدید به همان نقشه وصل می‌شود، بدون دست بردن در کل شبکه.'),
                      (2, 'h3', 'همه چیز سر جای درستش.', 'شبکه را برای مسئلهٔ واقعی شما طراحی کنیم.', S.final_actions())],
             'timeline': {'bounds': [.45, .75], 'stops': [.3, .6, 1],
                          'cam': [WIDE, [.45, .58, {'z': .84, 'x': 540, 'y': 390}]],
                          'dots': [['a4-pk', 'a4-dot', .05, .28]]}},
        ],
        'states': [
            ('a1', '.dk-f3-1 .mon', [1, 2], True, 'fill:var(--brand);stroke:#fff'),
            ('a1', '.click-ring', [1], False, 'animation:ring 1.6s var(--ease-out) infinite'),
            ('a1', '.svc', [1], True, 'opacity:1'),
            ('a1', '.pk', [1, 2], False, 'opacity:1'),
            ('a1', ':is(.flow,.riser,.uplink)', [2], True, 'stroke:var(--brand);stroke-dasharray:4 14;animation:flow 1.1s linear infinite'),
            ('a1', '.svc2', [2], True, 'opacity:1'),
            ('a2', '.node-ring', [0], False, 'stroke:#E2A35A;animation:ring 1.4s ease-out infinite'),
            ('a2', '.q', [0], True, 'opacity:1'),
            ('a2', '.node rect', [1, 2], True, 'fill:#3D1E1B;stroke:var(--alert)'),
            ('a2', '.room', [1, 2], True, 'fill:rgba(242,112,94,.10)'),
            ('a2', '.tg', [1, 2], True, 'stroke:rgba(242,112,94,.5)'),
            ('a2', '.warn', [1, 2], True, 'opacity:1'),
            ('a2', '.hunt', [2], False, 'opacity:1'),
            ('a2', '.unk', [2], True, 'opacity:1'),
            ('a3', '.L-cab', [0], False, 'opacity:.12'),
            ('a3', ':is(.ask,.hot,.hot-t)', [0], True, 'opacity:1'),
            ('a3', '.lane-t', [1, 2, 3, 4], True, 'opacity:1'),
            ('a3', '.cab.alt', [2, 3, 4], True, 'opacity:1'),
            ('a3', '.wifi', [2], True, 'opacity:1'),
            ('a3', '.tagc', [3, 4], True, 'opacity:1'),
            ('a3', ':is(.shield-box,.shield,.mon-p)', [4], True, 'opacity:1'),
            ('a3', ':is(.lane,.riser,.uplink,.alt)', [4], False, 'animation:flow 1.4s linear infinite'),
            ('a4', '.cab.alt', [0, 1, 2], True, 'opacity:1'),
            ('a4', '.shield-box', [0, 1, 2], True, 'opacity:.45'),
            ('a4', ':is(.brk,.ok-b)', [0], True, 'opacity:1'),
            ('a4', '.riser1', [0], True, 'stroke:var(--alert);opacity:.6;stroke-dasharray:6 10'),
            ('a4', '.pk', [0], False, 'opacity:1'),
            ('a4', '.newf', [1, 2], True, 'opacity:1;transform:none'),
            ('a4', ':is(.lane,.riser,.uplink,.alt)', [1, 2], False, 'animation:flow 1.4s linear infinite'),
        ],
        'css': """#a2 .room-f2{transition-delay:0ms}#a2 .room-f3{transition-delay:220ms}#a2 .room-f1{transition-delay:380ms}#a2 .room-b{transition-delay:540ms}
#a2 .warn-f2{transition-delay:120ms}#a2 .warn-f3{transition-delay:340ms}#a2 .warn-f1{transition-delay:500ms}
#a2 .unk{transition-delay:calc(var(--k) * 140ms)}""",
        'summary': {'h2': 'طراحی و اجرای شبکهٔ سازمانی در DaranX',
                    'paras': ['DaranX شبکهٔ سازمانی، یعنی شبکهٔ باسیم و بی‌سیم از دسترسی کاربران تا ارتباط دیتاسنتر، را بر اساس بار واقعی امروز و رشد یک تا سه سال آینده طراحی، اجرا و پشتیبانی می‌کند.',
                              'شبکه بستری است که ایمیل، فایل، نرم‌افزارهای داخلی، اینترنت، تلفن و سامانه‌های نظارتی روی آن کار می‌کنند. اگر درست طراحی نشده باشد، کندی و قطعی‌های پراکنده در جای‌جای سازمان پیدا می‌شود و عیب‌یابی سخت و پرهزینه است.'],
                    'bullets': ['طراحی پیش از خرید: انتخاب برند و مدل تجهیزات نتیجهٔ طراحی است، نه نقطهٔ شروع آن.',
                                'جداسازی منطقی بخش‌ها (VLAN) برای نظم و امنیت.', 'مسیرهای جایگزین و افزونگی برای بخش‌های حیاتی.',
                                'پوشش بی‌سیم یکنواخت و پایدار.', 'کابل‌کشی و مستندسازی منظم، و پشتیبانی که از همان مرحلهٔ طراحی در نظر گرفته می‌شود.']},
        'why': ['پایداری ارتباط و کاهش قطعی', 'مقیاس‌پذیری برای رشد بدون بازطراحی', 'بستری آماده برای امنیت و پایش'],
        'method': [('شناخت', 'کاربران، سرویس‌ها، بار امروز و رشد آینده را می‌سنجیم.'), ('طراحی', 'ساختار شبکه، جداسازی بخش‌ها و مسیرهای جایگزین را طراحی می‌کنیم.'),
                   ('اجرا', 'کابل‌کشی و نصب منظم، همراه با مستندات کامل.'), ('پشتیبانی', 'نگهداری و توسعهٔ شبکه، از همان روز تحویل.')],
        'faq': [('قبل از خرید تجهیزات طراحی می‌کنید؟', 'بله. اول نیاز را مشخص می‌کنیم: چند کاربر و چه سرویس‌هایی روی شبکه‌اند، بار امروز چقدر است و یک تا سه سال آینده چه تغییری می‌کند. انتخاب سوییچ، اکسس‌پوینت و کابل نتیجهٔ همین طراحی است.'),
                ('شبکهٔ موجود ما را هم بهبود می‌دهید؟', 'بله. نقاط ضعف شبکهٔ فعلی را ارزیابی می‌کنیم و آن را مرحله‌به‌مرحله بهینه می‌کنیم تا جریان کار سازمان متوقف نشود.'),
                ('شبکه را برای امنیت و پایش هم آماده می‌کنید؟', 'بله. جداسازی بخش‌ها و آماده‌بودن برای امنیت و مانیتورینگ از همان ابتدا در طراحی است، تا بعداً به بازطراحی پرهزینه نیاز نباشد.'),
                ('بعد از تحویل پشتیبانی دارید؟', 'بله. پشتیبانی از مرحلهٔ طراحی در نظر گرفته می‌شود و شبکه طوری ساخته می‌شود که نگهداری و توسعه‌اش ساده باشد.')],
        'related': {'h2': 'بقیهٔ حوزهٔ زیرساخت', 'links': [('دیتاسنتر و سرور', 'datacenter-server.html'), ('ذخیره‌سازی', 'storage.html'), ('برق و UPS', 'power-ups.html'),
                                                          ('زیرساخت پسیو', 'passive.html'), ('امنیت شبکه', 'network-security.html'), ('همهٔ حوزهٔ زیرساخت', 'infrastructure.html')]},
        'cta': {'h2': 'شبکه را برای مسئلهٔ واقعی شما طراحی کنیم.', 'p': 'اول نیاز شما را مشخص می‌کنیم، بعد می‌گوییم چه چیزی واقعاً لازم دارید.'},
    }
