/* DaranX network story: four acts in one building.
   Rule: frame() only sets transform attributes, circle positions and one CSS variable, all as pure
   functions of progress; scene() is handled by CSS through data-sc; reset() removes everything set here. */
(function () {
  var E = StoryEngine, U = E.util, $ = U.$, seg = U.seg, ease = U.ease, lerp = U.lerp, elerp = U.elerp;
  var vw = innerWidth, vh = innerHeight;
  var HOME = { x: 540, y: 470 };                         /* centre of building + internet */

  /* how much of the 1600x900 world is visible, and where the subject should sit */
  function view() {
    var k = Math.max(vw / 1600, vh / 900), Vw = vw / k, Vh = vh / k, wide = vw / vh > 1.05;
    var left = 800 - Vw / 2, top = 450 - Vh / 2;
    var fit = wide ? Math.min(1.12, Vw * .63 / 1000, Vh * .82 / 700) : Math.min(1, Vw * .96 / 980, Vh * .5 / 700);
    return { wide: wide, fit: fit, ax: wide ? left + Vw * .32 : 800, ay: wide ? top + Vh * .49 : top + Vh * .37, top: top, Vh: Vh };
  }
  function cam(z, fx, fy, ayOverride) {
    var v = view(), s = v.fit * z, ay = ayOverride === undefined ? v.ay : ayOverride;
    return 'translate(' + v.ax.toFixed(1) + ' ' + ay.toFixed(1) + ') scale(' + s.toFixed(4) + ') translate(' + (-fx).toFixed(1) + ' ' + (-fy).toFixed(1) + ')';
  }
  function mix(a, b, t) { return { z: elerp(a.z, b.z, t), x: lerp(a.x, b.x, t), y: lerp(a.y, b.y, t) }; }
  function along(path, dot, t) {
    var L = path.getTotalLength(), pt = path.getPointAtLength(L * U.clamp(t, 0, 1));
    dot.setAttribute('cx', pt.x.toFixed(1)); dot.setAttribute('cy', pt.y.toFixed(1));
  }
  function layout(w, h) { vw = w; vh = h; }

  /* ---------- act 1: one click, then every service on one network ---------- */
  var c1 = $('cam-a1'), copy = $('storyCopy'), p1 = $('pk-a1'), d1 = $('pk-dot-a1');
  var WIDE = { z: 1, x: HOME.x, y: HOME.y }, DESK = { z: 1.9, x: 430, y: 270 };
  E.act('a1', [.22, .6], {
    layout: layout,
    frame: function (p) {
      var t1 = ease(seg(p, .14, .38)), t2 = ease(seg(p, .62, .86)), v = view();
      /* hero: the building steps back (left on desktop, down on phones) to leave room for the headline */
      var HERO = v.wide ? { z: .84, x: HOME.x + 110, y: HOME.y } : { z: .86, x: HOME.x, y: HOME.y };
      var c = t2 > 0 ? mix(DESK, WIDE, t2) : mix(HERO, DESK, t1);
      var ay = v.wide ? undefined : lerp(v.top + v.Vh * .72, v.ay, Math.max(t1, seg(p, .05, .2)));
      c1.setAttribute('transform', cam(c.z, c.x, c.y, ay));
      var f = 1 - seg(p, .05, .15); copy.style.opacity = f; copy.style.visibility = f < .01 ? 'hidden' : 'visible';
      along(p1, d1, seg(p, .62, .97));
    },
    reset: function () { c1.removeAttribute('transform'); copy.removeAttribute('style'); d1.removeAttribute('cx'); d1.removeAttribute('cy'); }
  }, [0, .42, 1]);

  /* ---------- act 2: the unplanned network ---------- */
  var c2 = $('cam-a2'), w2 = $('w-a2'), hunt = $('hunt');
  var NODE = { z: 1.45, x: 820, y: 455 };
  var HUNT = [[420, 252], [700, 418], [330, 598], [860, 590], [640, 742]];
  E.act('a2', [.45, .75], {
    layout: layout,
    frame: function (p) {
      var t0 = ease(seg(p, .04, .28)), t1 = ease(seg(p, .45, .6));
      var c = t1 > 0 ? mix(NODE, WIDE, t1) : mix(WIDE, NODE, t0);
      c2.setAttribute('transform', cam(c.z, c.x, c.y));
      w2.style.setProperty('--draw', (1 - seg(p, 0, .26)).toFixed(3));
      var q = seg(p, .76, .98) * (HUNT.length - 1), i = Math.min(HUNT.length - 2, Math.floor(q)), f = ease(q - i);
      var hx = lerp(HUNT[i][0], HUNT[i + 1][0], f), hy = lerp(HUNT[i][1], HUNT[i + 1][1], f);
      hunt.setAttribute('transform', 'translate(' + hx.toFixed(1) + ' ' + hy.toFixed(1) + ')');
    },
    reset: function () { c2.removeAttribute('transform'); w2.style.removeProperty('--draw'); hunt.setAttribute('transform', 'translate(700 418)'); }
  }, [.3, .6, 1]);

  /* ---------- act 3: the method, drawn as a blueprint ---------- */
  var c3 = $('cam-a3'), RACKV = { z: 1.6, x: 680, y: 640 };
  E.act('a3', [.22, .4, .58, .76], {
    layout: layout,
    frame: function (p) {
      var tin = ease(seg(p, .58, .66)), tout = ease(seg(p, .76, .84));
      var c = tout > 0 ? mix(RACKV, WIDE, tout) : mix(WIDE, RACKV, tin);
      c3.setAttribute('transform', cam(c.z, c.x, c.y));
    },
    reset: function () { c3.removeAttribute('transform'); }
  }, [.1, .31, .49, .67, .9]);

  /* ---------- act 4: the same failure, then growth ---------- */
  var c4 = $('cam-a4'), p4 = $('pk-a4'), d4 = $('pk-dot-a4'), GROW = { z: .84, x: HOME.x, y: 390 };
  E.act('a4', [.45, .75], {
    layout: layout,
    frame: function (p) {
      var t = ease(seg(p, .45, .58)), c = mix(WIDE, GROW, t);
      c4.setAttribute('transform', cam(c.z, c.x, c.y));
      along(p4, d4, seg(p, .05, .28));
    },
    reset: function () { c4.removeAttribute('transform'); d4.removeAttribute('cx'); d4.removeAttribute('cy'); }
  }, [.3, .6, 1]);

  E.start();
})();
