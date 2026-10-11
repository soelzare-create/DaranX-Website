"""Drawing kit for the DaranX story pages.

Every page tells its story inside the same world: a cross-section of an
organisation's building (three floors, a basement server room, the outside).
The kit draws the building and the parts the stories need; page modules in
pages/ combine them per act. Everything is plain SVG strings.

World units: viewBox 0 0 1600 900. Ground at y=660. Elements that CSS or JS
must find get classes (never ids), so four copies of the world on one page
never clash; ids for JS movers are given by the page, prefixed with the act.
"""
import random
import re
from pathlib import Path

W, H = 1600, 900
X0, X1 = 200, 1000                    # building walls
TOP = 150                             # roof line
GROUND = 660
FLOORS = [                            # (id, top, bottom, label)
    ('f3', 150, 320, 'مدیریت و مالی'),
    ('f2', 320, 490, 'فروش و کارشناسان'),
    ('f1', 490, 660, 'پذیرش و دوربین‌ها'),
]
BASE = ('b', 660, 800, 'اتاق سرور')
ALL = FLOORS + [BASE]
RISER = 950                           # primary riser (right shaft)
RISER2 = 212                          # second path (left shaft)
RACK = (790, 676, 110, 116)           # x, y, w, h
CORE_Y, CORE2_Y, ROUTER_Y = 700, 728, 760
CLOUD = (115, 560)
DESKS = {                             # desk x (left edge, 80 wide) per floor
    'f3': [250, 370, 490, 610, 730],
    'f2': [250, 370, 490, 610, 730],
    'f1': [370, 490, 610],
}
CAMS = [505, 830]                     # ceiling cameras on f1
AP_X = 560
ICON_DIR = Path(__file__).with_name('icons')
USED_ICONS = set()


def fb(fid):
    return {f[0]: f[2] for f in ALL}[fid]


def ft(fid):
    return {f[0]: f[1] for f in ALL}[fid]


def mid(fid):
    return (ft(fid) + fb(fid)) / 2


def sw_xy(fid):
    """floor switch: sits on the riser near the floor slab"""
    return RISER - 25, fb(fid) - 50


def desk_port(fid, i):
    return DESKS[fid][i] + 40, fb(fid) - 39


def desk_mon(fid, i):
    """centre of a desk's monitor"""
    return DESKS[fid][i] + 40, fb(fid) - 69


# ---------------------------------------------------------------- primitives
def icon_symbols():
    out = []
    for name in sorted(USED_ICONS):
        body = re.sub(r'^<svg[^>]*>|</svg>\s*$', '', (ICON_DIR / f'{name}.svg').read_text().strip())
        out.append(f'<symbol id="i-{name}" viewBox="0 0 256 256">{body}</symbol>')
    return ''.join(out)


def use(icon, x, y, s, cls='ic'):
    if not (ICON_DIR / f'{icon}.svg').exists():
        raise KeyError(f'icon {icon} not in icons/')
    USED_ICONS.add(icon)
    c = f' class="{cls}"' if cls else ''
    return f'<use href="#i-{icon}" x="{x - s / 2:.0f}" y="{y - s / 2:.0f}" width="{s}" height="{s}"{c}/>'


def label(x, y, text, cls='lbl', anchor='middle'):
    return f'<text class="{cls}" x="{x:.0f}" y="{y:.0f}" text-anchor="{anchor}" direction="ltr">{text}</text>'


def text_w(text, size=17):
    """rough width of a Persian/Latin label in world units"""
    return len(text) * size * .56 + 8


def chip(cx, cy, text, cls='chip', icon=None, size=17):
    """a pill label centred on (cx, cy); icon sits at its right (reading start)"""
    w = text_w(text, size) + (30 if icon else 0) + 24
    x = cx - w / 2
    s = [f'<g class="{cls}"><rect x="{x:.0f}" y="{cy - 17:.0f}" width="{w:.0f}" height="34" rx="17"/>']
    if icon:
        s.append(use(icon, x + w - 22, cy, 20))
        s.append(label(x + w - 38, cy + 6, text, 'lbl chip-t', 'end'))
    else:
        s.append(label(cx, cy + 6, text, 'lbl chip-t'))
    s.append('</g>')
    return ''.join(s)


def pin(cx, cy, text=None, cls='pin', icon='warning', below=True):
    """a hotspot ring with an icon; its label goes below (or above), never on it"""
    s = [f'<g class="{cls}"><circle class="pin-r" cx="{cx}" cy="{cy}" r="24"/>{use(icon, cx, cy, 26)}']
    if text:
        s.append(chip(cx, cy + (48 if below else -48), text, 'chip pin-c'))
    s.append('</g>')
    return ''.join(s)


def group(cls, *parts, attrs=''):
    return f'<g class="{cls}"{attrs}>' + ''.join(parts) + '</g>'


def path(d, cls='cab', attrs=''):
    return f'<path class="{cls}" d="{d}"{attrs}/>'


def mover(pid, d, dot_id, r=9, cls='pk'):
    """an invisible path and the dot that the timeline moves along it"""
    x, y = re.match(r'M\s*([-\d.]+)[ ,]([-\d.]+)', d).groups()
    return (f'<path class="pk-path" id="{pid}" d="{d}"/>'
            f'<circle class="{cls}" id="{dot_id}" cx="{x}" cy="{y}" r="{r}"/>')


def bars(x, y, w, h, values, cls='bars'):
    """a small bar chart; bars grow with CSS (scaleY) when the scene arrives"""
    n = len(values)
    bw = w / (n * 1.6)
    s = [f'<g class="{cls}"><rect class="panel" x="{x}" y="{y}" width="{w}" height="{h}" rx="10"/>']
    for k, v in enumerate(values):
        bh = (h - 30) * v
        bx = x + 14 + k * bw * 1.6
        s.append(f'<rect class="bar" style="--k:{k}" x="{bx:.0f}" y="{y + h - 14 - bh:.0f}" width="{bw:.0f}" height="{bh:.0f}" rx="3"/>')
    s.append('</g>')
    return ''.join(s)


# ---------------------------------------------------------------- the building
def shell(labels=True, names=None):
    s = [f'<rect class="earth" x="-2000" y="{GROUND}" width="5600" height="2000"/>',
         f'<line class="ground" x1="-2000" y1="{GROUND}" x2="3600" y2="{GROUND}"/>']
    for fid, top, bot, name in ALL:
        s.append(f'<rect class="room room-{fid}" x="{X0}" y="{top}" width="{X1 - X0}" height="{bot - top}"/>')
    s.append(f'<rect class="shell" x="{X0}" y="{TOP}" width="{X1 - X0}" height="{BASE[2] - TOP}" rx="6"/>')
    for fid, top, bot, name in FLOORS:
        s.append(f'<line class="slab" x1="{X0}" y1="{bot}" x2="{X1}" y2="{bot}"/>')
    s.append(f'<rect class="shaft" x="{RISER - 14}" y="160" width="28" height="{BASE[2] - 170}" rx="4"/>')
    if labels:
        names = names or {}
        for fid, top, bot, name in ALL:
            s.append(label(X0 + 40, top + 32, names.get(fid, name), 'lbl flr', 'start'))
    return ''.join(s)


def desks(floors=('f3', 'f2', 'f1')):
    s = []
    for fid in floors:
        bot = fb(fid)
        for i, x in enumerate(DESKS[fid]):
            s.append(f'<g class="desk dk-{fid}-{i}">'
                     f'<rect class="mon" x="{x + 22}" y="{bot - 82}" width="36" height="26" rx="3"/>'
                     f'<rect class="stand" x="{x + 37}" y="{bot - 56}" width="6" height="10"/>'
                     f'<rect class="top" x="{x}" y="{bot - 46}" width="80" height="7" rx="2"/>'
                     f'<rect class="leg" x="{x + 6}" y="{bot - 39}" width="5" height="39"/>'
                     f'<rect class="leg" x="{x + 69}" y="{bot - 39}" width="5" height="39"/></g>')
    return ''.join(s)


def aps():
    return ''.join(f'<g class="ap"><rect x="{AP_X - 18}" y="{top + 8}" width="36" height="9" rx="4"/>'
                   f'{use("wifi-high", AP_X, top + 30, 24)}</g>' for fid, top, bot, _ in FLOORS)


def switches():
    s = []
    for fid, top, bot, _ in FLOORS:
        x, y = sw_xy(fid)
        s.append(f'<g class="sw sw-{fid}"><rect x="{x}" y="{y}" width="50" height="16" rx="3"/>'
                 + ''.join(f'<rect class="port" x="{x + 6 + k * 8}" y="{y + 6}" width="5" height="5"/>' for k in range(5))
                 + '</g>')
    return ''.join(s)


def cameras(cls='cam-dev'):
    return ''.join(f'<g class="{cls}">{use("video-camera", cx, 490 + 28, 30)}</g>' for cx in CAMS)


def phone():
    return f'<g class="phone-dev">{use("phone", 410, 660 - 66, 22)}</g>'


def rack(units=('core', 'core2', 'router')):
    x, y, w, h = RACK
    ys = {'core': CORE_Y, 'core2': CORE2_Y, 'router': ROUTER_Y}
    return (f'<g class="rack"><rect x="{x}" y="{y}" width="{w}" height="{h}" rx="5"/>'
            + ''.join(f'<rect class="unit {u}" x="{x + 8}" y="{ys[u]}" width="{w - 16}" height="16" rx="2"/>' for u in units)
            + '</g>')


def server_icon(x=726, y=735, s=64, cls='srv'):
    return f'<g class="{cls}">{use("hard-drives", x, y, s)}</g>'


def cloud(text='اینترنت'):
    return (f'<g class="cloud">{use("cloud", CLOUD[0], CLOUD[1], 120)}'
            + label(CLOUD[0], CLOUD[1] + 66, text, 'lbl sm') + '</g>')


def furniture(cams=True, ph=True, srv=True, cl=True, ap=True, sw=True, rk=True):
    return ''.join([desks(), aps() if ap else '', switches() if sw else '', cameras() if cams else '',
                    phone() if ph else '', rack() if rk else '', server_icon() if srv else '', cloud() if cl else ''])


# ---------------------------------------------------------------- cables
def neat_cables(lanes=False, uplink=True):
    """cables as a designed network: desk drops, floor runs, risers"""
    s = []
    for fid, top, bot, _ in FLOORS:
        lane = f' lane lane-{fid}' if lanes else ''
        for i, _x in enumerate(DESKS[fid]):
            px, py = desk_port(fid, i)
            s.append(f'<path class="cab flow{lane}" d="M{px} {py} V{bot - 8} H{RISER - 18} V{bot - 34}"/>')
        s.append(f'<path class="cab flow{" lane lane-wifi" if lanes else ""}" d="M{AP_X + 18} {top + 12} H{RISER - 30} V{bot - 50}"/>')
    for cx in CAMS:
        lane = ' lane lane-f1' if lanes else ''
        s.append(f'<path class="cab flow{lane}" d="M{cx} {490 + 14} V{490 + 4} H{RISER - 30} V{660 - 50}"/>')
    s.append(f'<path class="cab riser riser1" d="M{RISER} {fb("f3") - 34} V{CORE_Y + 8} H{RACK[0] + RACK[2] - 8}"/>')
    if uplink:
        s.append(f'<path class="cab uplink" d="M{RACK[0] + 8} {ROUTER_Y + 8} H{160} V{CLOUD[1] + 40}"/>')
    return ''.join(s)


def second_path():
    s = []
    for fid, top, bot, _ in FLOORS:
        x, y = sw_xy(fid)
        s.append(f'<path class="cab alt" d="M{x} {y + 8} H{x - 12} V{top + 44} H{RISER2} "/>')
    s.append(f'<path class="cab alt riser2" d="M{RISER2} {TOP + 44} V{CORE2_Y + 8} H{RACK[0] + 8}"/>')
    return ''.join(s)


def tangle(seed=7, node=(RISER - 6, 450)):
    """an unplanned network: every cable snakes to one cheap switch"""
    rnd = random.Random(seed)
    nx, ny = node
    s = []
    ends = [desk_port(f, i) for f, _, _, _ in FLOORS for i in range(len(DESKS[f]))]
    ends += [(AP_X, ft(f) + 14) for f, _, _, _ in FLOORS] + [(cx, 504) for cx in CAMS]
    for (px, py) in ends:
        c1 = (px + rnd.uniform(-160, 160), py + rnd.uniform(-90, 90))
        c2 = (nx + rnd.uniform(-260, 40), ny + rnd.uniform(-120, 120))
        s.append(f'<path class="cab tg" pathLength="1" d="M{px} {py} C{c1[0]:.0f} {c1[1]:.0f} {c2[0]:.0f} {c2[1]:.0f} {nx} {ny}"/>')
    s.append(f'<path class="cab tg one" pathLength="1" d="M{nx} {ny} C{nx + 30} {ny + 120} {nx - 40} {CORE_Y - 40} {RACK[0] + RACK[2] - 8} {CORE_Y + 8}"/>')
    return ''.join(s)


def node(at=(RISER - 6, 450), queue=True):
    nx, ny = at
    s = [f'<g class="node"><rect x="{nx - 30}" y="{ny - 11}" width="60" height="22" rx="4"/>'
         f'<circle class="node-ring" cx="{nx}" cy="{ny}" r="38"/></g>']
    if queue:
        s += [f'<circle class="q" cx="{nx - 46 - k * 15}" cy="{ny + 26}" r="5" style="--k:{k}"/>' for k in range(6)]
    return ''.join(s)


def uplink():
    return f'<path class="cab uplink" d="M{RACK[0] + 8} {ROUTER_Y + 8} H{160} V{CLOUD[1] + 40}"/>'


# ---------------------------------------------------------------- common overlays
def floor_warnings(texts, cls='warn'):
    """one alert line per floor, in the free band above the monitors"""
    s = []
    for fid, text in texts.items():
        top = ft(fid)
        s.append(f'<g class="{cls} {cls}-{fid}">{use("warning", 450, top + 60, 28)}'
                 + label(470, top + 68, text, 'lbl alert', 'start') + '</g>')
    return ''.join(s)


def asks(questions, x=680):
    """question chips, one per floor (we ask; we never invent the numbers)"""
    return ''.join(chip(x, ft(fid) + 29, q, 'chip ask', 'question') for fid, q in questions)


def hot(fids, text='حساس'):
    s = []
    for fid in fids:
        top, bot = ft(fid), fb(fid)
        s.append(f'<rect class="hot" x="{X0 + 6}" y="{top + 6}" width="{X1 - X0 - 12}" height="{bot - top - 12}" rx="8"/>')
        s.append(group('hot-t', f'<rect x="{X1 - 150}" y="{top + 12}" width="80" height="30" rx="15"/>',
                       label(X1 - 110, top + 33, text, 'lbl sm')))
    return ''.join(s)


def shield_box(cls='shield-box'):
    return (f'<rect class="{cls}" x="{X0 - 22}" y="{TOP - 22}" width="{X1 - X0 + 44}" height="{BASE[2] - TOP + 44}" rx="22"/>'
            + group('shield', use('shield-check', X1 + 2, TOP - 22, 54)))


def pulse_line(x=470, y=744, n=14, cls='mon-p', icon='eye'):
    wave = {5: -24, 6: 16, 7: -8}
    pts = ' '.join(f'{x + k * 16},{y + wave.get(k, 0)}' for k in range(n))
    return group(cls, f'<polyline points="{pts}"/>', use(icon, x - 24, y, 26))


def new_floor(cls='newf'):
    top, bot = -20, 150
    return group(cls, f'<rect class="room" x="{X0}" y="{top}" width="{X1 - X0}" height="{bot - top}"/>',
                 f'<rect class="shell nf" x="{X0}" y="{top}" width="{X1 - X0}" height="{bot - top}" rx="6"/>',
                 *[f'<g class="desk"><rect class="mon" x="{dx + 22}" y="{bot - 82}" width="36" height="26" rx="3"/>'
                   f'<rect class="top" x="{dx}" y="{bot - 46}" width="80" height="7" rx="2"/></g>' for dx in (250, 370, 490)],
                 f'<path class="cab lane lane-new" d="M290 {bot - 39} V{bot - 8} H{RISER} V{bot + 120}"/>',
                 label(X1 - 30, top + 34, 'طبقهٔ جدید', 'lbl flr', 'end'))


def world(act, layers, cls=''):
    """wrap layers (strings) into the act's SVG with its camera group"""
    return (f'<svg class="scene world w-{act} {cls}" id="w-{act}" viewBox="0 0 {W} {H}" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">'
            f'<rect class="sky" x="-2000" y="-2000" width="5600" height="4900"/>'
            f'<g class="cam" id="cam-{act}">{"".join(layers)}</g></svg>')


# ---------------------------------------------------------------- infrastructure parts
def srack(x, y=696, w=70, h=96, units=4, cls='srack', idle=()):
    """a server rack with units and status lights; units listed in idle are drawn dim"""
    s = [f'<g class="{cls}"><rect class="rk" x="{x}" y="{y}" width="{w}" height="{h}" rx="5"/>']
    uh = (h - 12) / units
    for k in range(units):
        uy = y + 6 + k * uh
        c = ' idle' if k in idle else ''
        s.append(f'<rect class="unit{c}" x="{x + 6}" y="{uy:.0f}" width="{w - 12}" height="{uh - 5:.0f}" rx="2"/>'
                 f'<circle class="led{c}" style="--k:{k}" cx="{x + w - 14}" cy="{uy + (uh - 5) / 2:.0f}" r="3"/>')
    s.append('</g>')
    return ''.join(s)


def host(x, y, w, h, vms=6, cls='host', vm_cls='vm'):
    """a physical host with virtual machines inside (cubes)"""
    cols = 3
    s = [f'<g class="{cls}"><rect class="rk" x="{x}" y="{y}" width="{w}" height="{h}" rx="8"/>', f'<g class="{vm_cls}s">']
    cw, ch = (w - 24) / cols, (h - 20) / ((vms + cols - 1) // cols)
    for k in range(vms):
        cx = x + 12 + (k % cols) * cw + cw / 2
        cy = y + 10 + (k // cols) * ch + ch / 2
        s.append(f'<g class="{vm_cls}" style="--k:{k}">{use("cube", cx, cy, min(cw, ch) * .8)}</g>')
    s.append('</g></g>')
    return ''.join(s)


def gauge(x, y, w, used, cls='gauge', text_used='', text_free='', id=None):
    """a capacity bar: used part solid, headroom dashed; --t (0..1) grows the used part"""
    uw = w * used
    i = f' id="{id}"' if id else ''
    s = [f'<g class="{cls}"{i}><rect class="g-all" x="{x}" y="{y}" width="{w}" height="26" rx="13"/>',
         f'<rect class="g-used" x="{x + w - uw:.0f}" y="{y}" width="{uw:.0f}" height="26" rx="13"/>']
    if text_used:
        s.append(label(x + w - uw / 2, y + 56, text_used, 'lbl sm'))
    if text_free:
        s.append(label(x + (w - uw) / 2, y + 56, text_free, 'lbl sm'))
    s.append('</g>')
    return ''.join(s)


def arrow(d, cls='arw', id=None):
    i = f' id="{id}"' if id else ''
    return f'<path class="{cls}"{i} d="{d}" pathLength="1" marker-end="url(#arrowhead)"/>'


def xmark(x, y, s=18, cls='brk'):
    return group(cls, f'<line x1="{x - s}" y1="{y - s}" x2="{x + s}" y2="{y + s}"/><line x1="{x + s}" y1="{y - s}" x2="{x - s}" y2="{y + s}"/>')


def ripple(cx, cy, r=60, cls='ripple'):
    return group(cls, *[f'<circle cx="{cx}" cy="{cy}" r="{r}" style="--k:{k}"/>' for k in range(3)])


def ups(x=560, y=708, cls='ups', id=None):
    """the UPS cabinet with its battery level (--t fills it)"""
    return group(cls, f'<rect class="rk" x="{x}" y="{y}" width="92" height="84" rx="6"/>',
                 f'<rect class="bat" x="{x + 14}" y="{y + 18}" width="64" height="26" rx="4"/>',
                 f'<rect class="bat-in" x="{x + 18}" y="{y + 22}" width="56" height="18" rx="2"/>',
                 label(x + 46, y + 70, 'UPS', 'lbl sm'), attrs=f' id="{id}"' if id else '')


def disks(x, y, cols=4, rows=2, cls='disks', bad=()):
    """a storage array: a grid of disks; disks listed in bad can turn red"""
    s = [f'<g class="{cls}"><rect class="rk" x="{x}" y="{y}" width="{cols * 34 + 14}" height="{rows * 40 + 14}" rx="6"/>']
    for r in range(rows):
        for c in range(cols):
            k = r * cols + c
            b = ' bad' if k in bad else ''
            s.append(f'<rect class="disk{b}" style="--k:{k}" x="{x + 8 + c * 34}" y="{y + 8 + r * 40}" width="28" height="34" rx="3"/>')
    s.append('</g>')
    return ''.join(s)
