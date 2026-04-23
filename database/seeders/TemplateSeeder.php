<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'business', 'coaching', 'real-estate', 'crypto',
            'ecommerce', 'health', 'finance', 'marketing',
            'education', 'consulting',
        ];

        $styles = ['high-ticket', 'lead-gen', 'product-launch', 'evergreen', 'live-event'];

        for ($i = 1; $i <= 50; $i++) {
            $category = $categories[($i - 1) % count($categories)];
            $style    = $styles[($i - 1) % count($styles)];
            $name     = "DFY Webinar Template {$i}";
            $slug     = Str::slug("dfy-webinar-template-{$i}");

            $template = Template::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name'             => $name,
                    'category'         => $category,
                    'conversion_style' => $style,
                    'thumbnail_url'    => "https://picsum.photos/seed/dfy-template-{$i}/800/450",
                    'default_palette'  => ['primary' => '#40E0D0', 'secondary' => '#FFAD00'],
                    'is_active'        => true,
                    'sort_order'       => $i,
                ]
            );

            $design = $this->buildOptinDesign(($i - 1) % 5, $name, $category, $style);

            $template->versions()->updateOrCreate(
                ['version' => 1],
                [
                    'optin_schema' => [
                        'html'       => $design['html'],
                        'css'        => $design['css'],
                        'hero'       => [
                            'headline'    => "{$name} — Save Your Seat",
                            'subheadline' => 'Learn the complete system in one focused webinar.',
                            'cta'         => 'Reserve My Spot',
                        ],
                    ],
                    'webinar_schema' => [
                        'title'       => "{$name} Webinar Room",
                        'description' => 'Watch the training and join the chat sidebar.',
                        'video'       => ['provider' => 'youtube', 'url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
                        'chat'        => ['position' => 'right', 'enabled' => true],
                    ],
                    'default_settings' => [
                        'chat_mode'          => 'simulated',
                        'allow_replay'       => true,
                        'double_opt_in'      => false,
                        'branding'           => ['primary' => '#40E0D0', 'secondary' => '#FFAD00'],
                        'chat_seed_messages' => [
                            ['author' => 'Moderator', 'message' => 'Welcome! Let us know where you are joining from 👋'],
                            ['author' => 'Host', 'message' => 'The webinar starts in just a few minutes — stay tuned!'],
                        ],
                    ],
                    'is_current' => true,
                ]
            );

            $template->versions()->where('version', '!=', 1)->update(['is_current' => false]);
        }
    }

    /* ─────────────────────────────────────────────────────────
     | 5 beautiful opt-in page designs (cycle by index % 5)
     ───────────────────────────────────────────────────────── */
    private function buildOptinDesign(int $variant, string $name, string $category, string $style): array
    {
        return match ($variant) {
            0 => $this->designDarkTurquoise($name, $category),
            1 => $this->designAmberDark($name, $category),
            2 => $this->designLightTeal($name, $category),
            3 => $this->designDeepPurple($name, $category),
            default => $this->designMinimalWhite($name, $category),
        };
    }

    /* ── Design 0: Dark Navy + Turquoise ── */
    private function designDarkTurquoise(string $name, string $category): array
    {
        $cat = ucfirst($category);
        $html = <<<HTML
<div class="dfy-page">
  <section class="dfy-hero">
    <div class="dfy-inner">
      <span class="dfy-badge">🎓 FREE {$cat} WEBINAR</span>
      <h1 class="dfy-headline">{$name}<br><span class="dfy-accent">— Save Your Spot Today</span></h1>
      <p class="dfy-sub">Discover the proven system that top {$category} professionals use to get results. Join us live for this exclusive training.</p>
      <div class="dfy-trust">
        <span>✓ 100% Free</span><span>✓ Instant Access</span><span>✓ Limited Spots</span>
      </div>
      <form class="dfy-form" data-locked-form="true">
        <input class="dfy-input" name="name" type="text" placeholder="Your full name" required />
        <input class="dfy-input" name="email" type="email" placeholder="Your best email address" required />
        <button class="dfy-btn" type="submit">Yes! Reserve My Free Spot →</button>
        <p class="dfy-privacy">🔒 100% secure &amp; confidential. No spam, ever.</p>
      </form>
    </div>
  </section>
</div>
HTML;

        $css = <<<CSS
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
.dfy-page{min-height:100vh;background:linear-gradient(140deg,#060d1a 0%,#0d2039 60%,#081a30 100%);display:flex;align-items:center;justify-content:center;padding:40px 16px}
.dfy-inner{max-width:560px;width:100%;text-align:center}
.dfy-badge{display:inline-block;background:rgba(255,80,80,.15);border:1px solid rgba(255,80,80,.3);color:#ff7070;padding:6px 18px;border-radius:100px;font-size:11px;font-weight:700;letter-spacing:.08em;margin-bottom:28px}
.dfy-headline{font-size:clamp(1.75rem,5vw,2.6rem);font-weight:900;color:#fff;line-height:1.15;margin-bottom:16px}
.dfy-accent{color:#40E0D0}
.dfy-sub{font-size:1rem;color:rgba(255,255,255,.6);line-height:1.75;margin-bottom:24px}
.dfy-trust{display:flex;gap:20px;justify-content:center;flex-wrap:wrap;margin-bottom:32px}
.dfy-trust span{font-size:13px;color:#40E0D0;font-weight:600}
.dfy-form{background:rgba(255,255,255,.05);border:1px solid rgba(64,224,208,.2);border-radius:20px;padding:32px;backdrop-filter:blur(12px)}
.dfy-input{display:block;width:100%;padding:14px 18px;border:1px solid rgba(255,255,255,.15);border-radius:10px;background:rgba(255,255,255,.07);color:#fff;font-size:15px;outline:none;margin-bottom:12px;transition:border-color .2s}
.dfy-input::placeholder{color:rgba(255,255,255,.35)}
.dfy-input:focus{border-color:#40E0D0;background:rgba(64,224,208,.07)}
.dfy-btn{width:100%;padding:16px;background:linear-gradient(135deg,#40E0D0,#2dc4b5);color:#060d1a;font-size:16px;font-weight:800;border:none;border-radius:10px;cursor:pointer;margin-top:4px;transition:transform .15s,opacity .15s;letter-spacing:.02em}
.dfy-btn:hover{transform:translateY(-1px);opacity:.92}
.dfy-privacy{margin-top:14px;font-size:11.5px;color:rgba(255,255,255,.3)}
CSS;

        return compact('html', 'css');
    }

    /* ── Design 1: Dark Amber / Warm Gold ── */
    private function designAmberDark(string $name, string $category): array
    {
        $cat = ucfirst($category);
        $html = <<<HTML
<div class="dfy-page">
  <section class="dfy-hero">
    <div class="dfy-inner">
      <span class="dfy-badge">⚡ EXCLUSIVE {$cat} TRAINING</span>
      <h1 class="dfy-headline">{$name}</h1>
      <p class="dfy-tagline">— Your Results Start Here —</p>
      <p class="dfy-sub">Join this free live webinar and get the insider strategies that are working right now in {$category}. Limited seats — register below.</p>
      <form class="dfy-form" data-locked-form="true">
        <label class="dfy-label">First Name</label>
        <input class="dfy-input" name="name" type="text" placeholder="Enter your name" required />
        <label class="dfy-label">Email Address</label>
        <input class="dfy-input" name="email" type="email" placeholder="Enter your email" required />
        <button class="dfy-btn" type="submit">Claim My Free Seat Now →</button>
      </form>
      <div class="dfy-footer-trust">
        <span>🔒 Secure</span><span>✓ Free Forever</span><span>⏰ Limited Time</span>
      </div>
    </div>
  </section>
</div>
HTML;

        $css = <<<CSS
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Georgia',serif}
.dfy-page{min-height:100vh;background:linear-gradient(160deg,#1a0800 0%,#2d1000 50%,#1a0800 100%);display:flex;align-items:center;justify-content:center;padding:40px 16px}
.dfy-inner{max-width:520px;width:100%;text-align:center}
.dfy-badge{display:inline-block;background:rgba(255,173,0,.15);border:1px solid rgba(255,173,0,.4);color:#FFAD00;padding:7px 20px;border-radius:4px;font-size:11px;font-weight:700;letter-spacing:.12em;margin-bottom:24px;font-family:-apple-system,sans-serif}
.dfy-headline{font-size:clamp(1.8rem,5vw,2.8rem);font-weight:900;color:#fff;line-height:1.15;margin-bottom:8px}
.dfy-tagline{font-size:1.1rem;color:#FFAD00;font-style:italic;margin-bottom:16px;letter-spacing:.04em}
.dfy-sub{font-size:.95rem;color:rgba(255,255,255,.55);line-height:1.7;margin-bottom:28px;font-family:-apple-system,sans-serif}
.dfy-form{background:rgba(255,173,0,.05);border:1px solid rgba(255,173,0,.2);border-radius:12px;padding:28px;text-align:left}
.dfy-label{display:block;font-size:11px;font-weight:700;letter-spacing:.08em;color:rgba(255,255,255,.5);margin-bottom:6px;text-transform:uppercase;font-family:-apple-system,sans-serif}
.dfy-input{display:block;width:100%;padding:13px 16px;border:1px solid rgba(255,173,0,.25);border-radius:8px;background:rgba(255,255,255,.06);color:#fff;font-size:15px;outline:none;margin-bottom:16px;font-family:-apple-system,sans-serif}
.dfy-input::placeholder{color:rgba(255,255,255,.3)}
.dfy-input:focus{border-color:#FFAD00}
.dfy-btn{width:100%;padding:15px;background:linear-gradient(135deg,#FFAD00,#e69500);color:#1a0800;font-size:15px;font-weight:800;border:none;border-radius:8px;cursor:pointer;transition:opacity .2s;font-family:-apple-system,sans-serif;letter-spacing:.02em}
.dfy-btn:hover{opacity:.88}
.dfy-footer-trust{display:flex;gap:24px;justify-content:center;flex-wrap:wrap;margin-top:20px}
.dfy-footer-trust span{font-size:12px;color:rgba(255,255,255,.35);font-family:-apple-system,sans-serif}
CSS;

        return compact('html', 'css');
    }

    /* ── Design 2: Light / Clean Teal ── */
    private function designLightTeal(string $name, string $category): array
    {
        $cat = ucfirst($category);
        $html = <<<HTML
<div class="dfy-page">
  <div class="dfy-card">
    <div class="dfy-card-left">
      <span class="dfy-badge">FREE WEBINAR</span>
      <h1 class="dfy-headline">{$name}</h1>
      <p class="dfy-sub">Your practical guide to mastering {$category}. This live webinar covers everything you need to get started and scale fast.</p>
      <ul class="dfy-list">
        <li>✅ Proven strategies that work in {$category}</li>
        <li>✅ Step-by-step actionable framework</li>
        <li>✅ Live Q&amp;A at the end</li>
        <li>✅ Free bonus resources for attendees</li>
      </ul>
    </div>
    <div class="dfy-card-right">
      <h2 class="dfy-form-title">Register for Free</h2>
      <p class="dfy-form-sub">Enter your details to secure your spot</p>
      <form class="dfy-form" data-locked-form="true">
        <input class="dfy-input" name="name" type="text" placeholder="Full name" required />
        <input class="dfy-input" name="email" type="email" placeholder="Email address" required />
        <button class="dfy-btn" type="submit">Register Now — It's Free!</button>
        <p class="dfy-privacy">🔒 No spam. Unsubscribe any time.</p>
      </form>
    </div>
  </div>
</div>
HTML;

        $css = <<<CSS
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
.dfy-page{min-height:100vh;background:linear-gradient(135deg,#f0fdf9 0%,#e6f9f6 100%);display:flex;align-items:center;justify-content:center;padding:40px 16px}
.dfy-card{max-width:880px;width:100%;background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.08);overflow:hidden;display:grid;grid-template-columns:1fr 1fr}
@media(max-width:640px){.dfy-card{grid-template-columns:1fr}}
.dfy-card-left{background:linear-gradient(160deg,#0d9488,#0f766e);padding:48px 36px;color:#fff}
.dfy-badge{display:inline-block;background:rgba(255,255,255,.2);color:#fff;padding:5px 14px;border-radius:100px;font-size:11px;font-weight:700;letter-spacing:.1em;margin-bottom:24px}
.dfy-headline{font-size:clamp(1.4rem,3vw,1.9rem);font-weight:800;line-height:1.25;margin-bottom:14px}
.dfy-sub{font-size:.9rem;opacity:.8;line-height:1.7;margin-bottom:24px}
.dfy-list{list-style:none;display:flex;flex-direction:column;gap:10px}
.dfy-list li{font-size:.9rem;opacity:.85}
.dfy-card-right{padding:48px 36px;display:flex;flex-direction:column;justify-content:center}
.dfy-form-title{font-size:1.4rem;font-weight:700;color:#0f172a;margin-bottom:6px}
.dfy-form-sub{font-size:.85rem;color:#64748b;margin-bottom:24px}
.dfy-input{display:block;width:100%;padding:13px 16px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:15px;outline:none;margin-bottom:12px;color:#0f172a;transition:border-color .2s}
.dfy-input:focus{border-color:#0d9488}
.dfy-btn{width:100%;padding:15px;background:linear-gradient(135deg,#0d9488,#0f766e);color:#fff;font-size:15px;font-weight:700;border:none;border-radius:10px;cursor:pointer;margin-top:4px;transition:opacity .2s}
.dfy-btn:hover{opacity:.88}
.dfy-privacy{margin-top:10px;font-size:12px;color:#94a3b8;text-align:center}
CSS;

        return compact('html', 'css');
    }

    /* ── Design 3: Deep Purple ── */
    private function designDeepPurple(string $name, string $category): array
    {
        $cat = ucfirst($category);
        $html = <<<HTML
<div class="dfy-page">
  <section class="dfy-hero">
    <div class="dfy-glow"></div>
    <div class="dfy-inner">
      <div class="dfy-eyebrow">🚀 {$cat} Mastery Series</div>
      <h1 class="dfy-headline">{$name}</h1>
      <p class="dfy-sub">The no-fluff, results-driven webinar that {$category} professionals are calling a game-changer. Seats are going fast.</p>
      <div class="dfy-stats">
        <div class="dfy-stat"><strong>1,200+</strong><span>Past Attendees</span></div>
        <div class="dfy-divider"></div>
        <div class="dfy-stat"><strong>4.9★</strong><span>Average Rating</span></div>
        <div class="dfy-divider"></div>
        <div class="dfy-stat"><strong>Free</strong><span>No Cost</span></div>
      </div>
      <form class="dfy-form" data-locked-form="true">
        <div class="dfy-row">
          <input class="dfy-input" name="name" type="text" placeholder="Full name" required />
          <input class="dfy-input" name="email" type="email" placeholder="Email address" required />
        </div>
        <button class="dfy-btn" type="submit">🎟 Claim My Free Seat</button>
        <p class="dfy-privacy">Your data is safe. We never share or sell it.</p>
      </form>
    </div>
  </section>
</div>
HTML;

        $css = <<<CSS
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
.dfy-page{min-height:100vh;background:linear-gradient(140deg,#0f0720 0%,#1e0a4e 50%,#0f0720 100%);display:flex;align-items:center;justify-content:center;padding:40px 16px;position:relative;overflow:hidden}
.dfy-glow{position:absolute;top:-20%;left:50%;transform:translateX(-50%);width:800px;height:800px;background:radial-gradient(circle,rgba(139,92,246,.25) 0%,transparent 70%);pointer-events:none}
.dfy-inner{max-width:600px;width:100%;text-align:center;position:relative;z-index:1}
.dfy-eyebrow{font-size:12px;font-weight:700;letter-spacing:.1em;color:#a78bfa;text-transform:uppercase;margin-bottom:20px}
.dfy-headline{font-size:clamp(1.9rem,5vw,2.8rem);font-weight:900;color:#fff;line-height:1.15;margin-bottom:16px}
.dfy-sub{font-size:.95rem;color:rgba(255,255,255,.55);line-height:1.75;margin-bottom:28px}
.dfy-stats{display:flex;justify-content:center;align-items:center;gap:0;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:16px 24px;margin-bottom:32px;flex-wrap:wrap;gap:8px}
.dfy-stat{text-align:center;padding:0 20px}
.dfy-stat strong{display:block;font-size:1.3rem;font-weight:800;color:#a78bfa}
.dfy-stat span{font-size:11px;color:rgba(255,255,255,.4);margin-top:2px;display:block}
.dfy-divider{width:1px;height:36px;background:rgba(255,255,255,.1)}
.dfy-form{background:rgba(255,255,255,.05);border:1px solid rgba(139,92,246,.3);border-radius:16px;padding:28px}
.dfy-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
@media(max-width:500px){.dfy-row{grid-template-columns:1fr}}
.dfy-input{width:100%;padding:13px 16px;border:1px solid rgba(255,255,255,.12);border-radius:8px;background:rgba(255,255,255,.07);color:#fff;font-size:14px;outline:none;transition:border-color .2s}
.dfy-input::placeholder{color:rgba(255,255,255,.3)}
.dfy-input:focus{border-color:#a78bfa}
.dfy-btn{width:100%;padding:15px;background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:#fff;font-size:15px;font-weight:700;border:none;border-radius:8px;cursor:pointer;transition:opacity .2s;letter-spacing:.02em}
.dfy-btn:hover{opacity:.88}
.dfy-privacy{margin-top:12px;font-size:11.5px;color:rgba(255,255,255,.25)}
CSS;

        return compact('html', 'css');
    }

    /* ── Design 4: Minimal White ── */
    private function designMinimalWhite(string $name, string $category): array
    {
        $cat = ucfirst($category);
        $html = <<<HTML
<div class="dfy-page">
  <div class="dfy-logo">DFY Webinar Forge</div>
  <section class="dfy-hero">
    <span class="dfy-badge">FREE LIVE WEBINAR · {$cat}</span>
    <h1 class="dfy-headline">{$name}</h1>
    <p class="dfy-sub">A focused, no-fluff webinar designed to help you get real results in {$category}. No experience needed.</p>
    <div class="dfy-features">
      <div class="dfy-feature"><div class="dfy-feat-icon">🎯</div><div><strong>Actionable</strong><p>Every minute is packed with takeaways</p></div></div>
      <div class="dfy-feature"><div class="dfy-feat-icon">⚡</div><div><strong>Fast Results</strong><p>Strategies you can implement today</p></div></div>
      <div class="dfy-feature"><div class="dfy-feat-icon">🎁</div><div><strong>Bonus</strong><p>Free resources for all attendees</p></div></div>
    </div>
    <form class="dfy-form" data-locked-form="true">
      <h2 class="dfy-form-title">Secure Your Spot</h2>
      <input class="dfy-input" name="name" type="text" placeholder="First name" required />
      <input class="dfy-input" name="email" type="email" placeholder="Email address" required />
      <button class="dfy-btn" type="submit">Register for Free →</button>
      <p class="dfy-privacy">No credit card required. Unsubscribe any time.</p>
    </form>
  </section>
</div>
HTML;

        $css = <<<CSS
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f8fafc}
.dfy-page{min-height:100vh;background:#f8fafc;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 16px}
.dfy-logo{font-size:13px;font-weight:700;color:#40E0D0;letter-spacing:.04em;margin-bottom:32px;text-transform:uppercase}
.dfy-hero{max-width:600px;width:100%;text-align:center}
.dfy-badge{display:inline-block;background:#f1f5f9;color:#40E0D0;border:1px solid rgba(64,224,208,.35);padding:6px 16px;border-radius:100px;font-size:11px;font-weight:700;letter-spacing:.08em;margin-bottom:24px}
.dfy-headline{font-size:clamp(1.8rem,5vw,2.6rem);font-weight:900;color:#0f172a;line-height:1.15;margin-bottom:16px}
.dfy-sub{font-size:1rem;color:#64748b;line-height:1.7;margin-bottom:32px;max-width:480px;margin-left:auto;margin-right:auto}
.dfy-features{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:40px;text-align:left}
@media(max-width:500px){.dfy-features{grid-template-columns:1fr}}
.dfy-feature{display:flex;gap:12px;align-items:flex-start;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px}
.dfy-feat-icon{font-size:1.4rem;flex-shrink:0}
.dfy-feature strong{font-size:.85rem;font-weight:700;color:#0f172a;display:block;margin-bottom:2px}
.dfy-feature p{font-size:.75rem;color:#94a3b8}
.dfy-form{background:#fff;border:1.5px solid #e2e8f0;border-radius:20px;padding:32px;box-shadow:0 8px 30px rgba(0,0,0,.06)}
.dfy-form-title{font-size:1.1rem;font-weight:700;color:#0f172a;margin-bottom:20px}
.dfy-input{display:block;width:100%;padding:13px 16px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:15px;outline:none;margin-bottom:12px;color:#0f172a;transition:border-color .2s}
.dfy-input:focus{border-color:#40E0D0}
.dfy-btn{width:100%;padding:15px;background:linear-gradient(135deg,#40E0D0,#2dc4b5);color:#0a0f1e;font-size:15px;font-weight:800;border:none;border-radius:10px;cursor:pointer;transition:opacity .15s,transform .15s;margin-top:4px}
.dfy-btn:hover{opacity:.9;transform:translateY(-1px)}
.dfy-privacy{margin-top:12px;font-size:11.5px;color:#cbd5e1}
CSS;

        return compact('html', 'css');
    }
}
