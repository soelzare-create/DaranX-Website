"""Scene graphics for the cinematic network page (network-story.html).

One world, drawn by code: a cross-section of an office building (three floors,
a basement server room, the internet outside). Each act gets its own copy of
the building with the cable layers it needs; CSS shows/hides layers per scene.

World units: viewBox 0 0 1600 900. Ground at y=660.
"""
import random
import re
from pathlib import Path

W, H = 1600, 900
X0, X1 = 200, 1000                    # building walls
GROUND = 660
FLOORS = [                            # (id, top, bottom, label)
    ('f3', 150, 320, 'مدیریت و مالی'),
    ('f2', 320, 490, 'فروش و کارشناسان'),
    ('f1', 490, 660, 'پذیرش و دوربین‌ها'),
]
BASE = ('b', 660, 800, 'اتاق سرور')
RISER = 950                           # primary riser (right shaft)
RISER2 = 212                          # second path (left shaft), from act 3 on
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


def fb(fid):
    return {f[0]: f[2] for f in FLOORS + [BASE]}[fid]


def ft(fid):
    return {f[0]: f[1] for f in FLOORS + [BASE]}[fid]


def sw_xy(fid):
    """floor switch: sits on the riser near the floor slab"""
    return RISER - 25, fb(fid) - 50


def icon_symbols():
    out = []
    for p in sorted(ICON_DIR.glob('*.svg')):
        body = re.sub(r'^<svg[^>]*>|</svg>\s*$', '', p.read_text().strip())
        out.append(f'<symbol id="i-{p.stem}" viewBox="0 0 256 256">{body}</symbol>')
    return ''.join(out)


def use(icon, x, y, s, cls=''):
    c = f' class="{cls}"' if cls else ''
    return f'<use href="#i-{icon}" x="{x - s / 2:.0f}" y="{y - s / 2:.0f}" width="{s}" height="{s}"{c}/>'


def label(x, y, text, cls='lbl', anchor='middle'):
    return f'<text class="{cls}" x="{x}" y="{y}" text-anchor="{anchor}" direction="ltr">{text}</text>'


# ---------------------------------------------------------------- building shell
def shell():
    s = []
    s.append(f'<rect class="earth" x="-2000" y="{GROUND}" width="5600" height="2000"/>')
    s.append(f'<line class="ground" x1="-2000" y1="{GROUND}" x2="3600" y2="{GROUND}"/>')
    for fid, top, bot, name in FLOORS + [BASE]:
        s.append(f'<rect class="room room-{fid}" x="{X0}" y="{top}" width="{X1 - X0}" height="{bot - top}"/>')
    s.append(f'<rect class="shell" x="{X0}" y="150" width="{X1 - X0}" height="{BASE[2] - 150}" rx="6"/>')
    for fid, top, bot, name in FLOORS:
        s.append(f'<line class="slab" x1="{X0}" y1="{bot}" x2="{X1}" y2="{bot}"/>')
    s.append(f'<rect class="shaft" x="{RISER - 14}" y="160" width="28" height="{BASE[2] - 170}" rx="4"/>')
    for fid, top, bot, name in FLOORS + [BASE]:
        s.append(label(X0 + 40, top + 32, name, 'lbl flr', 'start'))
    return ''.join(s)


def furniture():
    s = []
    for fid, top, bot, _ in FLOORS:
        for i, x in enumerate(DESKS[fid]):
            s.append(f'<g class="desk" id="dk-{fid}-{i}">'
                     f'<rect class="mon" x="{x + 22}" y="{bot - 82}" width="36" height="26" rx="3"/>'
                     f'<rect class="stand" x="{x + 37}" y="{bot - 56}" width="6" height="10"/>'
                     f'<rect class="top" x="{x}" y="{bot - 46}" width="80" height="7" rx="2"/>'
                     f'<rect class="leg" x="{x + 6}" y="{bot - 39}" width="5" height="39"/>'
                     f'<rect class="leg" x="{x + 69}" y="{bot - 39}" width="5" height="39"/></g>')
        s.append(f'<g class="ap"><rect x="{AP_X - 18}" y="{top + 8}" width="36" height="9" rx="4"/>'
                 f'{use("wifi-high", AP_X, top + 30, 24, "ic")}</g>')
        x, y = sw_xy(fid)
        s.append(f'<g class="sw" id="sw-{fid}"><rect x="{x}" y="{y}" width="50" height="16" rx="3"/>'
                 + ''.join(f'<rect class="port" x="{x + 6 + k * 8}" y="{y + 6}" width="5" height="5"/>' for k in range(5))
                 + '</g>')
    for cx in CAMS:
        s.append(f'<g class="cam-dev">{use("video-camera", cx, 490 + 28, 30, "ic")}</g>')
    s.append(f'<g class="phone-dev">{use("phone", 410, 660 - 66, 22, "ic")}</g>')
    x, y, w, h = RACK
    s.append(f'<g class="rack"><rect x="{x}" y="{y}" width="{w}" height="{h}" rx="5"/>'
             f'<rect class="unit core" id="core" x="{x + 8}" y="{CORE_Y}" width="{w - 16}" height="16" rx="2"/>'
             f'<rect class="unit core2" id="core2" x="{x + 8}" y="{CORE2_Y}" width="{w - 16}" height="16" rx="2"/>'
             f'<rect class="unit router" x="{x + 8}" y="{ROUTER_Y}" width="{w - 16}" height="16" rx="2"/></g>')
    s.append(f'<g class="srv">{use("hard-drives", 726, 735, 64, "ic")}</g>')
    s.append(f'<g class="cloud">{use("cloud", CLOUD[0], CLOUD[1], 120, "ic")}'
             + label(CLOUD[0], CLOUD[1] + 66, 'اینترنت', 'lbl sm') + '</g>')
    return ''.join(s)


# ---------------------------------------------------------------- cable layers
def desk_port(fid, i):
    x = DESKS[fid][i]
    return x + 40, fb(fid) - 39


def neat_cables(lanes=False):
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
    # primary riser: every floor switch down to the core
    s.append(f'<path class="cab riser" id="riser1" d="M{RISER} {fb("f3") - 34} V{CORE_Y + 8} H{RACK[0] + RACK[2] - 8}"/>')
    # internet uplink: router -> out through the basement wall -> cloud
    s.append(f'<path class="cab uplink" d="M{RACK[0] + 8} {ROUTER_Y + 8} H{160} V{CLOUD[1] + 40}"/>')
    return ''.join(s)


def second_path():
    s = []
    for fid, top, bot, _ in FLOORS:
        x, y = sw_xy(fid)
        s.append(f'<path class="cab alt" d="M{x} {y + 8} H{x - 12} V{top + 44} H{RISER2} "/>')
    s.append(f'<path class="cab alt" id="riser2" d="M{RISER2} {150 + 44} V{CORE2_Y + 8} H{RACK[0] + 8}"/>')
    return ''.join(s)


def tangle(seed=7):
    """an unplanned network: every cable snakes to one cheap switch on f2"""
    rnd = random.Random(seed)
    nx, ny = RISER - 6, 450
    s = []
    ends = [desk_port(f, i) for f, _, _, _ in FLOORS for i in range(len(DESKS[f]))]
    ends += [(AP_X, ft(f) + 14) for f, _, _, _ in FLOORS] + [(cx, 504) for cx in CAMS]
    for (px, py) in ends:
        c1 = (px + rnd.uniform(-160, 160), py + rnd.uniform(-90, 90))
        c2 = (nx + rnd.uniform(-260, 40), ny + rnd.uniform(-120, 120))
        s.append(f'<path class="cab tg" pathLength="1" d="M{px} {py} C{c1[0]:.0f} {c1[1]:.0f} {c2[0]:.0f} {c2[1]:.0f} {nx} {ny}"/>')
    s.append(f'<path class="cab tg one" pathLength="1" d="M{nx} {ny} C{nx + 30} {ny + 120} {nx - 40} {CORE_Y - 40} {RACK[0] + RACK[2] - 8} {CORE_Y + 8}"/>')
    s.append(f'<path class="cab uplink" d="M{RACK[0] + 8} {ROUTER_Y + 8} H{160} V{CLOUD[1] + 40}"/>')
    s.append(f'<g class="node" id="node"><rect x="{nx - 30}" y="{ny - 11}" width="60" height="22" rx="4"/>'
             f'<circle class="node-ring" cx="{nx}" cy="{ny}" r="38"/></g>')
    # queued packets waiting at the node
    for k in range(6):
        s.append(f'<circle class="q" cx="{nx - 46 - k * 15}" cy="{ny + 26}" r="5" style="--k:{k}"/>')
    return ''.join(s)


# ---------------------------------------------------------------- per-act overlays
def hero_fx():
    s = []
    fid, i = 'f3', 1
    px, py = desk_port(fid, i)
    x = DESKS[fid][i]
    s.append(f'<circle class="click-ring" cx="{x + 40}" cy="{fb(fid) - 69}" r="26"/>')
    s.append(f'<g class="svc">{use("file-text", x + 40, fb(fid) - 112, 30, "ic svc-i")}'
             f'{use("envelope-simple", x - 2, fb(fid) - 104, 26, "ic svc-i")}</g>')
    # the one packet: desk -> floor switch -> riser -> core -> router -> internet
    d = (f'M{px} {py} V{fb(fid) - 8} H{RISER - 18} V{fb(fid) - 34} H{RISER} V{CORE_Y + 8} H{RACK[0] + RACK[2] - 8}'
         f' V{ROUTER_Y + 8} H{160} V{CLOUD[1] + 40}')
    s.append(f'<path class="pk-path" id="pk-a1" d="{d}"/>')
    s.append(f'<circle class="pk" id="pk-dot-a1" cx="{px}" cy="{py}" r="9"/>')
    # every other service on the network
    for icon, ix, iy in [('envelope-simple', 650, 218), ('users-three', 530, 388), ('file-text', 650, 558),
                         ('video-camera', CAMS[1], 562), ('phone', 410, 556)]:
        s.append(f'<g class="svc2">{use(icon, ix, iy, 24, "ic")}</g>')
    return ''.join(s)


def problem_fx():
    s = []
    for fid, top, bot, _ in FLOORS:
        cx = (X0 + X1) / 2
        s.append(f'<g class="warn warn-{fid}">{use("warning", 450, top + 60, 28, "ic")}'
                 + label(470, top + 68, {'f3': 'کند', 'f2': 'قطعی', 'f1': 'خطای مبهم'}[fid], 'lbl alert', 'start') + '</g>')
    s.append(f'<g class="hunt" id="hunt">{use("magnifying-glass", 0, 0, 74, "ic")}</g>')
    for k, (qx, qy) in enumerate([(420, 270), (700, 430), (330, 610), (860, 600), (640, 760)]):
        s.append(f'<g class="unk" style="--k:{k}"><circle cx="{qx}" cy="{qy}" r="15"/>{label(qx, qy + 7, "؟", "lbl q-t")}</g>')
    return ''.join(s)


def plan_fx():
    s = []
    # scene 0: questions first (no numbers invented: these are the questions we ask)
    qs = [('f3', 'چند کاربر؟'), ('f2', 'چه سرویس‌هایی؟'), ('f1', 'بار امروز؟'), ('b', 'رشد تا سه سال؟')]
    for fid, q in qs:
        top = ft(fid)
        s.append(f'<g class="ask"><rect x="600" y="{top + 12}" width="160" height="34" rx="17"/>'
                 f'{use("question", 740, top + 29, 20, "ic")}'
                 + label(722, top + 36, q, 'lbl ask-t', 'end') + '</g>')
    for fid in ('f3', 'b'):
        top, bot = ft(fid), fb(fid)
        s.append(f'<rect class="hot" x="{X0 + 6}" y="{top + 6}" width="{X1 - X0 - 12}" height="{bot - top - 12}" rx="8"/>')
        s.append(f'<g class="hot-t"><rect x="{X1 - 150}" y="{top + 12}" width="80" height="30" rx="15"/>'
                 + label(X1 - 110, top + 33, 'حساس', 'lbl sm') + '</g>')
    # scene 1: lanes (labels sit in the free band above the monitors)
    for fid, name in [('f3', 'مالی'), ('f2', 'فروش'), ('f1', 'دوربین‌ها')]:
        top = ft(fid)
        s.append(f'<g class="lane-t lane-t-{fid}"><rect x="{X1 - 196}" y="{top + 54}" width="96" height="28" rx="14"/>'
                 + label(X1 - 148, top + 73, name, 'lbl sm') + '</g>')
    s.append(f'<g class="lane-t lane-t-wifi"><rect x="{AP_X + 30}" y="{150 + 58}" width="110" height="28" rx="14"/>'
             + label(AP_X + 85, 150 + 77, 'Wi-Fi', 'lbl sm') + '</g>')
    # scene 2: coverage
    for fid, top, bot, _ in FLOORS:
        s.append(f'<ellipse class="wifi" cx="{AP_X + 20}" cy="{(top + bot) / 2 + 6}" rx="330" ry="{(bot - top) / 2 - 12}"/>')
    # scene 3: labelled cables + tidy rack
    n = 0
    for fid, top, bot, _ in FLOORS:
        for i, x in enumerate(DESKS[fid]):
            n += 1
            s.append(f'<g class="tagc"><rect x="{x + 47}" y="{bot - 30}" width="30" height="16" rx="3"/>'
                     + label(x + 62, bot - 18, f'{n:02d}', 'lbl tag-t') + '</g>')
    # scene 4: security + monitoring
    s.append(f'<rect class="shield-box" x="{X0 - 22}" y="{150 - 22}" width="{X1 - X0 + 44}" height="{BASE[2] - 150 + 44}" rx="22"/>')
    s.append(f'<g class="shield">{use("shield-check", X1 + 2, 150 - 22, 54, "ic")}</g>')
    wave = {5: -24, 6: 16, 7: -8}
    pts = ' '.join(f'{470 + k * 16},{744 + wave.get(k, 0)}' for k in range(14))
    s.append(f'<g class="mon-p"><polyline points="{pts}"/>{use("eye", 446, 744, 26, "ic")}</g>')
    return ''.join(s)


def test_fx():
    s = []
    # the same failure: the primary riser breaks between f2 and the basement
    s.append(f'<g class="brk"><line x1="{RISER - 18}" y1="560" x2="{RISER + 18}" y2="596"/><line x1="{RISER + 18}" y1="560" x2="{RISER - 18}" y2="596"/></g>')
    fid, i = 'f3', 1
    px, py = desk_port(fid, i)
    x, y = sw_xy(fid)
    d = (f'M{px} {py} V{fb(fid) - 8} H{RISER - 18} V{fb(fid) - 34} H{x} V{y + 8} H{x - 12} V{ft(fid) + 44} H{RISER2}'
         f' V{CORE2_Y + 8} H{RACK[0] + 8}')
    s.append(f'<path class="pk-path" id="pk-a4" d="{d}"/>')
    s.append(f'<circle class="pk" id="pk-dot-a4" cx="{px}" cy="{py}" r="9"/>')
    s.append(f'<g class="ok-b"><rect x="610" y="{ft("f2") + 6}" width="190" height="34" rx="17"/>'
             f'{use("check-circle", 776, ft("f2") + 23, 22, "ic")}'
             + label(752, ft("f2") + 30, 'کار ادامه دارد', 'lbl sm', 'end') + '</g>')
    # growth: one more floor plugs into the same plan
    top, bot = -20, 150
    s.append(f'<g class="newf" id="newf"><rect class="room" x="{X0}" y="{top}" width="{X1 - X0}" height="{bot - top}"/>'
             f'<rect class="shell nf" x="{X0}" y="{top}" width="{X1 - X0}" height="{bot - top}" rx="6"/>'
             + ''.join(f'<g class="desk"><rect class="mon" x="{dx + 22}" y="{bot - 82}" width="36" height="26" rx="3"/>'
                       f'<rect class="top" x="{dx}" y="{bot - 46}" width="80" height="7" rx="2"/></g>' for dx in (250, 370, 490))
             + f'<path class="cab lane lane-new" d="M290 {bot - 39} V{bot - 8} H{RISER} V{bot + 120}"/>'
             + label(X1 - 30, top + 34, 'طبقهٔ جدید', 'lbl flr', 'end') + '</g>')
    return ''.join(s)


def world(act):
    """act: a1 (hero), a2 (problem), a3 (method), a4 (test)"""
    layers = [f'<g class="L-shell">{shell()}</g>']
    if act == 'a1':
        layers.append(f'<g class="L-cab">{neat_cables()}</g>')
    elif act == 'a2':
        layers.append(f'<g class="L-cab">{tangle()}</g>')
    else:
        layers.append(f'<g class="L-alt">{second_path()}</g>')
        layers.append(f'<g class="L-cab">{neat_cables(lanes=True)}</g>')
    layers.append(f'<g class="L-furn">{furniture()}</g>')
    fx = {'a1': hero_fx, 'a2': problem_fx, 'a3': plan_fx, 'a4': lambda: plan_fx() + test_fx()}[act]()
    layers.append(f'<g class="L-fx">{fx}</g>')
    return (f'<svg class="scene world w-{act}" id="w-{act}" viewBox="0 0 {W} {H}" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">'
            f'<rect class="sky" x="-2000" y="-2000" width="5600" height="4900"/>'
            f'<g class="cam" id="cam-{act}">{"".join(layers)}</g></svg>')
