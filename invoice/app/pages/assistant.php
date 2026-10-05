<?php
/** Chat assistant: documents, receipts, purchases, payments out and financial reports, in plain Persian. */
defined('APP_DIR') || exit;

$configured = setting('assistant_api_key') !== '';
layout_start('دستیار', 'assistant');
?>
<style>
  .chat-card{display:flex;flex-direction:column;gap:0;padding:0;overflow:hidden}
  .chat-log{min-height:46vh;max-height:60vh;overflow-y:auto;padding:1.1rem;display:flex;flex-direction:column;gap:.7rem}
  .msg{max-width:84%;padding:.6rem .9rem;border-radius:14px;line-height:1.9;white-space:pre-wrap;word-break:break-word}
  .msg.bot{white-space:normal}
  .msg.bot.wide{max-width:100%;width:100%}
  .msg p{margin:0}
  .msg p + p,.msg p + .md-tbl,.msg .md-tbl + p,.msg ul,.msg ol{margin-top:.5rem}
  .msg ul,.msg ol{margin-bottom:0;padding-inline-start:1.2rem}
  .msg .md-h{font-weight:800}
  .msg code{background:var(--surface,#fff);border-radius:6px;padding:0 .35rem;font-size:.9em}
  .md-tbl{overflow-x:auto;margin-top:.5rem;border:1px solid var(--line,#dce4ee);border-radius:10px;background:var(--surface,#fff)}
  .md-tbl table{border-collapse:collapse;width:100%;font-size:.88rem;line-height:1.7}
  .md-tbl th,.md-tbl td{padding:.4rem .7rem;text-align:right;white-space:nowrap;border-bottom:1px solid var(--line,#dce4ee)}
  .md-tbl th{font-weight:700;color:var(--ink-faint,#5e6e86);background:var(--surface-2,#eaf0f7)}
  .md-tbl tr:last-child td{border-bottom:0}
  .chat-chips{display:flex;flex-wrap:wrap;gap:.4rem;padding:.7rem .7rem 0;border-top:1px solid var(--line,#dce4ee)}
  .chat-chips .btn{font-size:.82rem}
  .chat-chips + .chat-input{border-top:0}
  @media (max-width:640px){
    .chat-chips{flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;-webkit-mask-image:linear-gradient(to right,transparent 0,#000 24px);mask-image:linear-gradient(to right,transparent 0,#000 24px)}
    .chat-chips::-webkit-scrollbar{display:none}
    .chat-chips .btn{flex:none}
  }
  .msg.user{align-self:flex-start;background:var(--grad-soft,linear-gradient(120deg,#153A62,#3E7BB6));color:#fff;border-bottom-right-radius:4px}
  .msg.bot{align-self:flex-end;background:var(--surface-2,#eaf0f7);color:var(--ink,#12233a);border-bottom-left-radius:4px}
  .msg.bot.err{background:#fdeaea;color:#8a1f1f}
  .msg .acts{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.5rem}
  .msg .acts a{font-size:.85rem;font-weight:700;background:#fff;color:var(--navy,#153A62);border:1px solid var(--line,#dce4ee);border-radius:999px;padding:.25rem .7rem;text-decoration:none}
  .chat-typing{align-self:flex-end;color:var(--ink-faint,#5e6e86);font-size:.9rem;padding:.2rem .4rem}
  .chat-input{display:flex;gap:.5rem;border-top:1px solid var(--line,#dce4ee);padding:.7rem}
  .chat-input textarea{flex:1;resize:none;min-height:46px;max-height:140px;border:1px solid var(--line,#dce4ee);border-radius:12px;padding:.6rem .8rem;font:inherit;background:var(--surface,#fff);color:inherit}
  .chat-input button{align-self:flex-end}
  .chat-hint{margin:.8rem 0 0;color:var(--ink-faint,#5e6e86);font-size:.9rem;line-height:2}
  .chat-hint code{font-family:inherit;font-size:.88rem;background:var(--surface-2,#eaf0f7);border-radius:6px;padding:.1rem .45rem}
</style>
<main class="page narrow">
  <div class="head"><h1>دستیار</h1></div>

<?php if (!$configured): ?>
  <section class="card">
    <h2>دستیار هنوز فعال نیست</h2>
    <p>برای استفاده از چت، یک کلید API از Anthropic در صفحهٔ تنظیمات وارد کنید. کلید فقط روی سرور ذخیره می‌شود و مصرف آن هزینهٔ جداگانه دارد.</p>
    <a class="btn btn-primary" href="<?= e(url('settings')) ?>">رفتن به تنظیمات</a>
  </section>
<?php else: ?>
  <section class="card chat-card">
    <div id="chat" class="chat-log">
      <div class="msg bot">سلام! هر کاری را که در برنامه انجام می‌دهید، می‌توانید اینجا بنویسید: پیش‌فاکتور و فاکتور، ثبت دریافتی، ثبت خرید برای یک فاکتور، ثبت پرداختی (مستقیم یا سربار) و هر گزارش مالی که لازم دارید. برای گزارش‌های پرکاربرد، دکمه‌های پایین را بزنید.</div>
    </div>
    <?php if (can('reports')): ?>
    <div class="chat-chips" aria-label="گزارش‌های آماده">
<?php foreach ([
    'سود و زیان این ماه' => 'گزارش سود و زیان این ماه را بده',
    'سود فاکتورها' => 'سود تک‌تک فاکتورهای این ماه را بده، کم‌سودترین اول',
    'بدهکاران' => 'چه کسانی به ما بدهکارند و بدهی‌شان چقدر قدیمی است؟',
    'بدهی به فروشنده‌ها' => 'به فروشنده‌ها بابت خریدها چقدر بدهکاریم؟',
    'هزینه‌های این ماه' => 'پرداختی‌های این ماه را به تفکیک نوع و بابت بده',
    'روند ماهانه' => 'روند ماه‌به‌ماه فروش، هزینه و سود امسال را بده',
    'فروش هر مشتری' => 'فروش امسال به تفکیک مشتری',
    'پرفروش‌ترین اقلام' => 'پرفروش‌ترین اقلام امسال کدام‌اند؟',
] as $label => $ask): ?>
      <button type="button" class="btn btn-ghost btn-sm" data-ask="<?= e($ask) ?>"><?= e($label) ?></button>
<?php endforeach; ?>
    </div>
    <?php endif; ?>
    <form id="chatForm" class="chat-input" data-csrf="<?= e(csrf_token()) ?>" data-api="<?= e(url('assistant_api')) ?>">
      <textarea id="chatMsg" placeholder="مثلاً: برای آقای احمدی ۵ میلیون دریافتی نقدی ثبت کن" rows="1" autocomplete="off"></textarea>
      <button class="btn btn-primary" type="submit">ارسال</button>
    </form>
  </section>
  <p class="chat-hint">
    نمونه‌ها:
    <code>برای شرکت پارس‌داده پیش‌فاکتور بزن: ۱۰ عدد سوییچ ۸ پورت هرکدام ۳ میلیون</code>،
    <code>برای فاکتور INV-0012 از ایران‌نت ۴۰ میلیون کابل خریدیم، ۱۵ میلیونش را کارت به کارت دادیم</code>،
    <code>۲ میلیون پول پیک فاکتور ۱۲ را نقدی دادیم</code>،
    <code>اجارهٔ مهر ۳۵ میلیون پرداخت شد</code>،
    <code>سود و زیان شهریور</code>،
    <code>سود فاکتور INV-0012 چقدر شد؟</code>
  </p>
<?php endif; ?>
</main>
<?php
layout_end($configured ? ['assistant.js'] : []);
