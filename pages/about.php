<?php if (!defined('CARPARTS_ACCESS')) define('CARPARTS_ACCESS', 1); ?>
<div class="content-box">
<h3>About &amp; Help</h3>

<div style="max-width:700px;font-size:14px;line-height:1.75;color:var(--color-text);">

<!-- ── What is this site? ── -->
<h4 style="margin-top:0;">What is Car Parts DB?</h4>
<p>
    Car Parts DB is a <strong>marketplace for used car parts</strong>, run by a car club.
    Think of it like a second-hand shop — but just for car parts, and run by enthusiasts
    for enthusiasts.
</p>
<p>
    Anyone can <strong>browse</strong> the listings for free, no account needed.
    You can search by car brand, model, year, condition, or just type a keyword.
    Each listing shows photos, a price, a part number, and details about the part.
</p>
<p>
    If you have parts you want to sell or show off, you can <strong>create a free account</strong>
    and add your own listings. You can upload photos, set a price (or mark it as
    "display only"), and keep track of everything in your personal parts catalogue.
</p>
<p>
    When someone is interested in one of your parts, they can send you a message
    directly through the site — no email addresses are shared publicly.
</p>

<hr style="margin:24px 0;border:none;border-top:1px solid var(--color-content-border);">

<!-- ── Sign-up ── -->
<h4>How does signing up work?</h4>
<ol style="margin:0 0 12px 18px;padding:0;">
    <li style="margin-bottom:8px;">
        Click <strong><a href="index.php?navigate=signup">Sign up</a></strong> in the menu.
        Fill in your email address, a display name, and a password.
    </li>
    <li style="margin-bottom:8px;">
        You will receive a <strong>confirmation email</strong> with a link.
        Click that link to activate your account.
        (Check your spam folder if it doesn't arrive within a few minutes.)
    </li>
    <li style="margin-bottom:8px;">
        Once confirmed, <strong>log in</strong> via the Login link and you're ready to go.
    </li>
</ol>
<p style="font-size:13px;color:#888;">
    Your email address is only used for account-related messages (confirmation, replies to your questions).
    It is never shown publicly.
</p>

<hr style="margin:24px 0;border:none;border-top:1px solid var(--color-content-border);">

<!-- ── Parts catalogue ── -->
<h4>Your own parts catalogue</h4>
<p>
    After logging in, you can manage your own parts via <strong>My Parts</strong> in the menu.
    Here's how it works:
</p>
<ul style="margin:0 0 12px 18px;padding:0;">
    <li style="margin-bottom:6px;">
        Click <strong>Add a Part</strong> to create a new listing. Fill in the car brand and model
        the part fits, give it a title, describe the condition, and add a price — or leave the price
        blank if you just want to show it.
    </li>
    <li style="margin-bottom:6px;">
        Upload <strong>photos</strong> from the listing page. Multiple photos are supported;
        drag to reorder them.
    </li>
    <li style="margin-bottom:6px;">
        Toggle <strong>For Sale</strong> if you're actively selling. Uncheck it to list the part
        as display-only — still visible, but without a price or "contact seller" option.
    </li>
    <li style="margin-bottom:6px;">
        Toggle <strong>Visible</strong> to control whether anyone else can see the listing.
        While unchecked, only you can see it — useful while you're still filling in the details.
    </li>
    <li style="margin-bottom:6px;">
        When a part sells, click <strong>Mark as sold</strong>. The listing stays in the database
        (so the history is kept) but disappears from the public browse view.
    </li>
    <li style="margin-bottom:6px;">
        Your <strong>My Parts</strong> page shows all your listings in one overview, with thumbnails,
        status badges, and view counts (how many times each listing was viewed).
    </li>
</ul>

<hr style="margin:24px 0;border:none;border-top:1px solid var(--color-content-border);">

<!-- ── Messaging ── -->
<h4>The messaging system</h4>
<p>
    Each listing has a <strong>Q&amp;A / contact section</strong> at the bottom.
    Visitors can send a question or offer to the seller directly through the site.
</p>
<ul style="margin:0 0 12px 18px;padding:0;">
    <li style="margin-bottom:6px;">
        <strong>Asking a question:</strong> scroll to the bottom of a listing and fill in the
        contact form. You can send a message without an account, but if you are logged in your
        name is filled in automatically and you will see the seller's reply on the listing page.
    </li>
    <li style="margin-bottom:6px;">
        <strong>Receiving messages (seller):</strong> when someone contacts you, you get a
        notification bar at the bottom of every page and an unread count in the Messages menu.
        Open the listing to read the message and reply inline.
    </li>
    <li style="margin-bottom:6px;">
        <strong>Replying:</strong> as the seller, click <em>Reply</em> next to a question.
        Your reply is posted on the listing page and the buyer receives an email notification
        (if they left an email address).
    </li>
    <li style="margin-bottom:6px;">
        <strong>Inbox:</strong> <a href="index.php?navigate=mymessages">My Messages</a> shows
        all conversations you are part of — both questions you sent and messages you received —
        sorted by the most recent activity.
    </li>
    <li style="margin-bottom:6px;">
        <strong>Conversation limits:</strong> to keep inboxes manageable, each seller can
        receive a limited number of conversations. If a seller's inbox is full, the contact
        form on their listings will say so. Individual threads also have a maximum number of
        replies to prevent endlessly long conversations.
    </li>
</ul>

<hr style="margin:24px 0;border:none;border-top:1px solid var(--color-content-border);">

<!-- ── Cookies / GDPR ── -->
<h4>Cookies &amp; privacy (GDPR)</h4>
<p>
    This site uses only <strong>functional cookies</strong> — the minimum needed to make it work.
    No advertising, no tracking, no data sold to third parties.
</p>
<table style="width:100%;border-collapse:collapse;font-size:13px;margin-top:8px;">
    <tr style="background:var(--color-nav-hover-bg);">
        <th style="padding:7px 10px;text-align:left;border-bottom:1px solid var(--color-content-border);">Cookie</th>
        <th style="padding:7px 10px;text-align:left;border-bottom:1px solid var(--color-content-border);">Purpose</th>
        <th style="padding:7px 10px;text-align:left;border-bottom:1px solid var(--color-content-border);">Duration</th>
    </tr>
    <tr>
        <td style="padding:7px 10px;border-bottom:1px solid var(--color-content-border);font-family:monospace;">PHPSESSID</td>
        <td style="padding:7px 10px;border-bottom:1px solid var(--color-content-border);">Keeps you logged in during your visit. Also protects forms against cross-site attacks (CSRF).</td>
        <td style="padding:7px 10px;border-bottom:1px solid var(--color-content-border);">Browser session</td>
    </tr>
    <tr style="background:var(--color-input-bg);">
        <td style="padding:7px 10px;border-bottom:1px solid var(--color-content-border);font-family:monospace;">snldb_theme</td>
        <td style="padding:7px 10px;border-bottom:1px solid var(--color-content-border);">Remembers your chosen colour theme so it is applied on your next visit.</td>
        <td style="padding:7px 10px;border-bottom:1px solid var(--color-content-border);">1 year</td>
    </tr>
    <tr>
        <td style="padding:7px 10px;border-bottom:1px solid var(--color-content-border);font-family:monospace;">snldb_theme_dark</td>
        <td style="padding:7px 10px;border-bottom:1px solid var(--color-content-border);">Remembers whether your chosen theme is a dark or light theme (used to avoid a flash of wrong colours on page load).</td>
        <td style="padding:7px 10px;border-bottom:1px solid var(--color-content-border);">1 year</td>
    </tr>
    <tr style="background:var(--color-input-bg);">
        <td style="padding:7px 10px;font-family:monospace;">cpdb_browse_view</td>
        <td style="padding:7px 10px;">Remembers whether you prefer the list or tile view in Browse Parts (used when you are not logged in).</td>
        <td style="padding:7px 10px;">1 year</td>
    </tr>
</table>
<p style="margin-top:12px;">
    No personal data is shared with external parties.
    For the full privacy statement, see the
    <a href="index.php?navigate=privacyverklaring">Privacyverklaring</a>.
</p>

<hr style="margin:24px 0;border:none;border-top:1px solid var(--color-content-border);">

<!-- ── Questions ── -->
<h4>Still have a question?</h4>
<p>
    Use the <a href="index.php?navigate=address">Contact</a> page to get in touch.
</p>

</div>
</div>
