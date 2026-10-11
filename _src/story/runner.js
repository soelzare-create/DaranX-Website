/* DaranX story runner: plays the timeline each page declares in window.STORY.
   Per act: camera keyframes, dots moving along paths, lines drawing in, markers moving through
   waypoints and progress variables for CSS. Everything is a pure function of the act's progress,
   so scrolling back, jumping and resizing all work; reset() removes everything set here. */
(function () {
  var E = window.StoryEngine; if (!E) return;
  var U = E.util, $ = U.$, seg = U.seg, ease = U.ease, lerp = U.lerp, elerp = U.elerp, clamp = U.clamp;
  var vw = innerWidth, vh = innerHeight;
  var DEF = { z: 1, x: 540, y: 470 };

  function view() {
    var k = Math.max(vw / 1600, vh / 900), Vw = vw / k, Vh = vh / k, wide = vw / vh > 1.05;
    var left = 800 - Vw / 2, top = 450 - Vh / 2;
    var fit = wide ? Math.min(1.12, Vw * .63 / 1000, Vh * .82 / 700) : Math.min(1, Vw * .96 / 980, Vh * .5 / 700);
    return { wide: wide, fit: fit, ax: wide ? left + Vw * .32 : 800, ay: wide ? top + Vh * .49 : top + Vh * .37, top: top, Vh: Vh };
  }
  function tf(c, ay) {
    var v = view(), s = v.fit * c.z;
    return 'translate(' + v.ax.toFixed(1) + ' ' + (ay === undefined ? v.ay : ay).toFixed(1) + ') scale(' + s.toFixed(4) + ') translate(' + (-c.x).toFixed(1) + ' ' + (-c.y).toFixed(1) + ')';
  }
  function mix(a, b, t) { return { z: elerp(a.z, b.z, t), x: lerp(a.x, b.x, t), y: lerp(a.y, b.y, t) }; }
  function camAt(cam, p, hero) {
    var from = cam[0] || DEF, first = 0;
    if (hero) { var w = view().wide; from = w ? { z: from.z * .84, x: from.x + 110, y: from.y } : { z: from.z * .86, x: from.x, y: from.y }; }
    var out = from;
    for (var i = 1; i < cam.length; i++) {
      var s = cam[i], t = ease(seg(p, s[0], s[1]));
      if (i === 1) first = t;
      if (t <= 0) break;
      out = mix(from, s[2], t); from = s[2];
    }
    return { c: out, first: first };
  }
  function along(path, dot, t) {
    var L = path.getTotalLength(), pt = path.getPointAtLength(L * clamp(t, 0, 1));
    dot.setAttribute('cx', pt.x.toFixed(1)); dot.setAttribute('cy', pt.y.toFixed(1));
  }
  function through(pts, t) {
    var q = clamp(t, 0, 1) * (pts.length - 1), i = Math.min(pts.length - 2, Math.floor(q)), f = ease(q - i);
    return [lerp(pts[i][0], pts[i + 1][0], f), lerp(pts[i][1], pts[i + 1][1], f)];
  }

  var cfg = window.STORY || { acts: [] };
  cfg.acts.forEach(function (a) {
    var camEl = $('cam-' + a.id), copy = a.hero ? $('storyCopy') : null;
    var dots = (a.dots || []).map(function (d) { return [$(d[0]), $(d[1]), d[2], d[3]]; });
    var draws = (a.draws || []).map(function (d) { return [$(d[0]), d[1], d[2]]; });
    var moves = (a.moves || []).map(function (m) { return [$(m[0]), m[1], m[2], m[3]]; });
    var vars = (a.vars || []).map(function (m) { return [$(m[0]), m[1], m[2], m[3]]; });
    E.act(a.id, a.bounds, {
      layout: function (w, h) { vw = w; vh = h; },
      frame: function (p) {
        var r = camAt(a.cam || [DEF], p, a.hero), v = view(), ay;
        if (a.hero && !v.wide) ay = lerp(v.top + v.Vh * .72, v.ay, Math.max(r.first, seg(p, .05, .2)));
        if (camEl) camEl.setAttribute('transform', tf(r.c, ay));
        if (copy) { var f = 1 - seg(p, .05, .15); copy.style.opacity = f; copy.style.visibility = f < .01 ? 'hidden' : 'visible'; }
        dots.forEach(function (d) { if (d[0] && d[1]) along(d[0], d[1], seg(p, d[2], d[3])); });
        draws.forEach(function (d) { if (d[0]) d[0].style.setProperty('--draw', (1 - seg(p, d[1], d[2])).toFixed(3)); });
        moves.forEach(function (m) { if (!m[0]) return; var xy = through(m[1], seg(p, m[2], m[3])); m[0].setAttribute('transform', 'translate(' + xy[0].toFixed(1) + ' ' + xy[1].toFixed(1) + ')'); });
        vars.forEach(function (m) { if (m[0]) m[0].style.setProperty('--' + m[1], seg(p, m[2], m[3]).toFixed(3)); });
      },
      reset: function () {
        if (camEl) camEl.removeAttribute('transform');
        if (copy) copy.removeAttribute('style');
        dots.forEach(function (d) { if (d[1]) { d[1].removeAttribute('cx'); d[1].removeAttribute('cy'); } });
        draws.forEach(function (d) { if (d[0]) d[0].style.removeProperty('--draw'); });
        moves.forEach(function (m) { if (m[0]) m[0].setAttribute('transform', 'translate(' + m[1][m[1].length - 1].join(' ') + ')'); });
        vars.forEach(function (m) { if (m[0]) m[0].style.removeProperty('--' + m[1]); });
      }
    }, a.stops);
  });
  E.start();

  /* phone menu */
  var btn = $('menuBtn'), bar = $('topbar');
  if (btn && bar) {
    btn.addEventListener('click', function () { var o = bar.classList.toggle('open'); btn.setAttribute('aria-expanded', o ? 'true' : 'false'); });
    bar.addEventListener('click', function (e) { if (e.target.closest('.nav a')) { bar.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && bar.classList.contains('open')) { bar.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); btn.focus(); } });
  }
})();
