<?php
/** Chat assistant: issue proformas/invoices and record payments in plain Persian. */
defined('APP_DIR') || exit;

$configured = setting('assistant_api_key') !== '';
layout_start('دستیار', 'assistant');
?>
<style>
  .chat-card{display:flex;flex-direction:column;gap:0;padding:0;overflow:hidden}
  .chat-log{min-height:46vh;max-height:60vh;overflow-y:auto;padding:1.1rem;display:flex;flex-direction:column;gap:.7rem}
  .msg{max-width:84%;padding:.6rem .9rem;border-radius:14px;line-height:1.9;white-space:pre-wrap;word-break:break-word}
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
  .chat-hint code{background:var(--surface-2,#eaf0f7);border-radius:6px;padding:.05rem .4rem}
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
      <div class="msg bot">سلام! می‌توانم پیش‌فاکتور بزنم، فاکتور صادر کنم، پیش‌فاکتور را به فاکتور تبدیل کنم و دریافتی ثبت کنم. چه کاری انجام بدهم؟</div>
    </div>
    <form id="chatForm" class="chat-input" data-csrf="<?= e(csrf_token()) ?>" data-api="<?= e(url('assistant_api')) ?>">
      <textarea id="chatMsg" placeholder="مثلاً: برای آقای احمدی ۵ میلیون دریافتی نقدی ثبت کن" rows="1" autocomplete="off"></textarea>
      <button class="btn btn-primary" type="submit">ارسال</button>
    </form>
  </section>
  <p class="chat-hint">
    نمونه‌ها:
    <code>برای شرکت پارس‌داده پیش‌فاکتور بزن: ۱۰ عدد سوییچ ۸ پورت هرکدام ۳ میلیون</code> ·
    <code>پیش‌فاکتور QOT-0007 را به فاکتور تبدیل کن</code> ·
    <code>مانده حساب آقای رضایی چقدره؟</code>
  </p>
<?php endif; ?>
</main>
<?php
layout_end($configured ? ['assistant.js'] : []);
