<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TemplateSeeder extends Seeder
{
    private const VIDEO_EMBEDS = [
        'https://www.youtube.com/embed/dQw4w9WgXcQ',
        'https://www.youtube.com/embed/9No-FiEInLA',
        'https://www.youtube.com/embed/fLexgOxsZu0',
        'https://www.youtube.com/embed/ysz5S6PUM-U',
        'https://www.youtube.com/embed/aqz-KE-bpKQ',
    ];

    public function run(): void
    {
        $categories = [
            'business', 'coaching', 'real-estate', 'crypto',
            'ecommerce', 'health', 'finance', 'marketing',
            'education', 'consulting',
        ];

        $styles = ['high-ticket', 'lead-gen', 'product-launch', 'evergreen', 'live-event'];
        $offers = $this->offerData();

        for ($i = 1; $i <= 51; $i++) {
            $offer    = $offers[$i - 1] ?? null;
            $category = $offer ? $this->getOfferCategory($offer['name']) : $categories[($i - 1) % count($categories)];
            $style    = $styles[($i - 1) % count($styles)];
            $name     = $offer['name'] ?? "DFY Webinar Template {$i}";
            $slug     = Str::slug($name);
            $videoUrl = $offer['video_url'] ?? self::VIDEO_EMBEDS[($i - 1) % count(self::VIDEO_EMBEDS)];
            $webinarTitle = $offer['webinar_title'] ?? "How to grow your {$category} business in 90 days";
            $webinarDescription = $offer
                ? $this->buildOfferDescription($offer)
                : "Step-by-step {$category} roadmap to get faster results, avoid common mistakes, and scale with confidence.";
            $webinarCtaLabel = 'sign up right away';
            $webinarCtaUrl = $offer['jv_page'] ?? 'https://example.com/next-step';
            $affiliateRequestLink = $offer['affiliate_request_link'] ?? null;
            $jvPage = $offer['jv_page'] ?? null;

            $timingRow = $this->templateWebinarOfferTiming()[$i - 1] ?? null;
            $webinarDurationSeconds = $timingRow
                ? $this->hmsToSeconds($timingRow['webinar'])
                : null;
            $primaryOfferSeconds = $timingRow
                ? $this->hmsToSeconds($timingRow['offer'])
                : 3600;
            $offerDisplayName = $offer
                ? (string) preg_replace('/\s+Offer$/i', '', (string) $offer['name'])
                : $name;

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

            $design = $this->buildOptinDesign(($i - 1) % 7, $offer, $name, $category, $style);

            $template->versions()->updateOrCreate(
                ['version' => 1],
                [
                    'optin_schema' => [
                        'html'       => $design['html'],
                        'css'        => $design['css'],
                        'hero'       => [
                            'headline'    => '🔥100% FREE TRAINING REVEALS:',
                            'subheadline' => $offer['optin_intro'] ?? 'Learn the complete system in one focused webinar.',
                            'cta'         => 'Reserve My Spot',
                        ],
                        'what_youll_discover' => $offer['bullet_points'] ?? [],
                    ],
                    'webinar_schema' => [
                        'title'       => $webinarTitle,
                        'description' => $webinarDescription,
                        'video'       => ['provider' => 'youtube', 'url' => $videoUrl],
                        'chat'        => ['position' => 'right', 'enabled' => true],
                    ],
                    'default_settings' => [
                        'webinar_title'      => $webinarTitle,
                        'webinar_description'=> $webinarDescription,
                        'video_url'          => $videoUrl,
                        'webinar_duration_seconds' => $webinarDurationSeconds,
                        'webinar_cta_label'  => $webinarCtaLabel,
                        'webinar_cta_url'    => $webinarCtaUrl,
                        'affiliate_request_link' => $affiliateRequestLink,
                        'jv_page'             => $jvPage,
                        'offers' => $this->buildDefaultOffersForTemplate(
                            $offerDisplayName,
                            $primaryOfferSeconds,
                            $webinarDurationSeconds,
                        ),
                        'chat_mode'          => 'simulated',
                        'allow_replay'       => true,
                        'redirect_enabled'   => false,
                        'redirect_url'       => '',
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

    /**
     * Parse "H:M:S", "M:S", or seconds-only segments from template timing notes.
     */
    private function hmsToSeconds(string $hms): int
    {
        $hms = trim($hms);
        $parts = array_map('intval', explode(':', $hms));
        $c = count($parts);

        if ($c === 1) {
            return max(0, $parts[0]);
        }

        if ($c === 2) {
            return max(0, ($parts[0] * 60) + $parts[1]);
        }

        return max(0, ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2]);
    }

    /**
     * Webinar length + primary offer drop time per seeded template (indexes 0–50 = templates #1–#51).
     *
     * @return list<array{webinar: string, offer: string}>
     */
    private function templateWebinarOfferTiming(): array
    {
        return [
            ['webinar' => '2:23:19', 'offer' => '1:03:37'],
            ['webinar' => '1:58:11', 'offer' => '1:03:00'],
            ['webinar' => '2:17:34', 'offer' => '1:03:45'],
            ['webinar' => '1:12:35', 'offer' => '30:47'],
            ['webinar' => '1:41:50', 'offer' => '1:23:48'],
            ['webinar' => '2:22:22', 'offer' => '1:30:00'],
            ['webinar' => '1:55:49', 'offer' => '1:18:00'],
            ['webinar' => '2:23:19', 'offer' => '1:03:37'],
            ['webinar' => '1:59:13', 'offer' => '1:20:50'],
            ['webinar' => '1:50:42', 'offer' => '1:17:54'],
            ['webinar' => '2:06:27', 'offer' => '1:21:12'],
            ['webinar' => '2:48:59', 'offer' => '1:01:59'],
            ['webinar' => '1:47:38', 'offer' => '57:20'],
            ['webinar' => '1:20:19', 'offer' => '1:05:40'],
            ['webinar' => '2:00:50', 'offer' => '1:11:20'],
            ['webinar' => '1:26:44', 'offer' => '1:03:10'],
            ['webinar' => '2:12:55', 'offer' => '1:07:25'],
            ['webinar' => '1:10:40', 'offer' => '1:02:30'],
            ['webinar' => '1:46:46', 'offer' => '1:16:00'],
            ['webinar' => '1:05:29', 'offer' => '1:00:00'],
            ['webinar' => '1:43:35', 'offer' => '1:01:00'],
            ['webinar' => '1:52:17', 'offer' => '1:09:00'],
            ['webinar' => '1:59:28', 'offer' => '1:07:00'],
            ['webinar' => '1:51:39', 'offer' => '56:40'],
            ['webinar' => '1:13:19', 'offer' => '47:20'],
            ['webinar' => '1:39:54', 'offer' => '1:05:50'],
            ['webinar' => '1:49:22', 'offer' => '1:15:26'],
            ['webinar' => '2:04:46', 'offer' => '1:11:45'],
            ['webinar' => '1:44:55', 'offer' => '1:05:50'],
            ['webinar' => '1:31:22', 'offer' => '1:20:33'],
            ['webinar' => '1:54:04', 'offer' => '1:12:12'],
            ['webinar' => '2:17:50', 'offer' => '1:15:00'],
            ['webinar' => '2:04:07', 'offer' => '1:04:40'],
            ['webinar' => '1:59:02', 'offer' => '1:03:37'],
            ['webinar' => '1:53:10', 'offer' => '55:20'],
            ['webinar' => '1:41:52', 'offer' => '56:37'],
            ['webinar' => '2:03:36', 'offer' => '54:38'],
            ['webinar' => '1:22:24', 'offer' => '1:04:18'],
            ['webinar' => '1:47:10', 'offer' => '55:55'],
            ['webinar' => '1:24:12', 'offer' => '1:08:55'],
            ['webinar' => '1:43:07', 'offer' => '1:10:48'],
            ['webinar' => '1:21:40', 'offer' => '1:17:22'],
            ['webinar' => '2:23:19', 'offer' => '1:03:37'],
            ['webinar' => '2:09:45', 'offer' => '1:22:01'],
            ['webinar' => '1:52:27', 'offer' => '1:06:33'],
            ['webinar' => '1:53:12', 'offer' => '1:22:50'],
            ['webinar' => '1:56:26', 'offer' => '1:28:30'],
            ['webinar' => '1:48:04', 'offer' => '1:03:30'],
            ['webinar' => '2:11:40', 'offer' => '1:19:45'],
            ['webinar' => '1:58:58', 'offer' => '1:18:10'],
            ['webinar' => '1:55:35', 'offer' => '1:06:29'],
        ];
    }

    /**
     * @return list<array{title: string, description: string, cta_label: string, cta_url: string, placement: string, timing_seconds: int, enabled: bool}>
     */
    private function buildDefaultOffersForTemplate(
        string $offerDisplayName,
        int $primaryOfferSeconds,
        ?int $webinarDurationSeconds,
    ): array {
        $chatOfferSeconds = max(300, $primaryOfferSeconds - 900);
        $pinnedOfferSeconds = max(300, $primaryOfferSeconds);
        $popupOfferSeconds = $webinarDurationSeconds !== null
            ? max($pinnedOfferSeconds + 120, min($pinnedOfferSeconds + 900, $webinarDurationSeconds - 300))
            : $pinnedOfferSeconds + 900;

        $title = "Get {$offerDisplayName} Bundle Deal Now!";
        $description = 'Get the all-inclusive bundle deal right away at the webinar only discounted price.';

        return [
            [
                'title' => $title,
                'description' => $description,
                'cta_label' => 'Get Deal',
                'cta_url' => '',
                'placement' => 'chat',
                'timing_seconds' => $chatOfferSeconds,
                'enabled' => true,
            ],
            [
                'title' => $title,
                'description' => $description,
                'cta_label' => 'Get Deal',
                'cta_url' => '',
                'placement' => 'pinned',
                'timing_seconds' => $pinnedOfferSeconds,
                'enabled' => true,
            ],
            [
                'title' => $title,
                'description' => $description,
                'cta_label' => 'Get Deal',
                'cta_url' => '',
                'placement' => 'popup',
                'timing_seconds' => $popupOfferSeconds,
                'enabled' => true,
            ],
        ];
    }

    private function getOfferCategory(string $name): string
    {
        $map = [
            // Education / info-products / publishing
            'GuruOS Offer'               => 'education',
            'KidBooks Ai Offer'          => 'education',
            'eBook Valet Offer'          => 'education',
            'Cohortia offer'             => 'education',
            'Writix Offer'               => 'education',

            // Ecommerce / product visuals
            'Creativo ai 2.0 Offer'      => 'ecommerce',
            'Showcase Ai offer'          => 'ecommerce',

            // Consulting / agency / local business
            'Agentic Agency Offer'       => 'consulting',
            'ClientForce Offer'          => 'consulting',
            'Outrich Offer'              => 'consulting',
            'LocalMator Offer'           => 'consulting',
            'Micro Content Agency Offer' => 'consulting',
            'AiGency Valet Offer'        => 'consulting',
            'Local Ai Fleet Offer'       => 'consulting',

            // SaaS / software / AI platforms
            'AiWrappers Offer'           => 'business',
            'OmniMint Ai offer'          => 'business',
            'InstantlyClaw Offer'        => 'business',
            'OpenClaw Cracked Offer'     => 'business',
            'CogneX Ai Offer'            => 'business',
            'Helpira Offer'              => 'business',
            'AI Flip Domains Offer'      => 'business',
            'Prezent IQ Offer'           => 'business',

            // Marketing / traffic / content / social media
            'GEO Optimizer Offer'        => 'marketing',
            'SerpSling Ai Offer'         => 'marketing',
            'Traffic Magnets Offer'      => 'marketing',
            'Cinemation Offer'           => 'marketing',
            'Hooked Ai Offer'            => 'marketing',
            'CleverAi Studio Offer'      => 'marketing',
            'ViralCharacters Offer'      => 'marketing',
            'GramGennies Offer'          => 'marketing',
            'Synthesys Actors Offer'     => 'marketing',
            'Reddify Ai Offer'           => 'marketing',
            'Imimic Offer'               => 'marketing',
            'AiPodVids Offer'            => 'marketing',
            'AiDirectors Offer'          => 'marketing',
            'InstaEngine Ai Offer'       => 'marketing',
            'SuperClips Ai offer'        => 'marketing',
            'TokPrime Ai Offer'          => 'marketing',
            'OminiSitesAi offer'         => 'marketing',
            'ChannelBuilder Offer'       => 'marketing',
            'GoBeaver Ai Offer'          => 'marketing',
            'Qroq Offer'                 => 'marketing',
            'AiSeller Offer'             => 'marketing',
            'AffiliateReel Offer'        => 'marketing',
            'Articalize Offer'           => 'marketing',
            'OhSoReal Offer'             => 'marketing',
            'Vidatia Offer'              => 'marketing',
            'ViralReel Offer'            => 'marketing',
            'Ai Talker Offer'            => 'marketing',
            'MagicPods Ai Offer'         => 'marketing',
            'Buzz Agent Offer'           => 'marketing',
        ];

        return $map[$name] ?? 'business';
    }

    private function buildOfferDescription(array $offer): string
    {
        $description = $offer['optin_heading']."\n\n".$offer['optin_intro']."\n\nWhat You'll Discover On This FREE Training:\n";

        foreach ($offer['bullet_points'] as $point) {
            $description .= "- {$point}\n";
        }

        return trim($description);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function offerData(): array
    {
        return [
            [
                'name' => 'GuruOS Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the world’s first AI operating system that builds your courses, memberships, communities, traffic campaigns, and launch funnels for you — helping beginners create profitable info businesses faster than ever.',
                'bullet_points' => [
                    'How to use AI agents to automatically create, design, and launch high-quality courses and digital products',
                    'The breakthrough “Operating System” technology that trains AI on your unique style, brand, and business so everything works together intelligently',
                    'How to build AI-powered communities with referral gamification that attract, engage, and grow members automatically',
                    'The simple system for generating leads, traffic, and sales using AI-driven content and social campaigns',
                    'How to deploy AI chatbots and coaches that engage visitors, answer questions, and even accept payments automatically',
                    'How beginners are using AI agents to launch scalable information businesses without being experts or showing their face',
                    'The “Agency Model” that lets you sell AI-powered course creation and automation services to businesses for high-ticket monthly retainers',
                    'Includes access to the proven $20M+ launch blueprint from Ben Murray showing how to create and scale winning offers fast',

                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/wEAY-IC3WLw',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/437655',
                'jv_page' => 'https://guruos.ai/jv',
            ],
            [
                'name' => 'Agentic Agency Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the first “agentic” AI platform that turns any local business website into a fully automated funnel, AI calling, and follow-up system that captures, nurtures, and books leads 24/7 — completely hands-free.',
                'bullet_points' => [
                    'How to turn any local business URL into a live high-converting funnel in under 5 minutes using AI automation',
                    'The breakthrough “Speed-To-Lead™” technology that instantly calls new leads within seconds — while most businesses take 47 hours to respond',
                    'How AI voice agents hold real two-way conversations using the business’s own services, pricing, and information',
                    'The automated follow-up system that nurtures leads across email, SMS, and AI voice until appointments get booked',
                    'How to generate leads and provide high-ticket AI automation services to local businesses with the included commercial license',
                    'The simple “paste-a-URL” system that removes the need for manual funnel building, copywriting, and technical setup',
                    'How to use AI-generated social content, SMS campaigns, and chatbots to increase engagement and conversions automatically',
                    'The client management system that allows you to run multiple business campaigns from one dashboard',
                    'How beginners are using AI automation to launch profitable local marketing agencies without employees or complex setups',
                    'Includes AI funnel builder, AI voice calling, smart drip automation, SMS campaigns, chatbot technology, and more — all inside one platform with Agentic Agency',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/sTlIfAGbKeg',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/438051',
                'jv_page' => 'https://getagenticagency.com/jv',
            ],
            [
                'name' => 'AI Flip Domains Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the AI-powered system that helps beginners identify high-potential domain names, estimate resale value, list them on major marketplaces, and flip them for profit without guessing or technical skills.',
                'bullet_points' => [
                    'How to tap into the multi-billion-dollar domain flipping industry without needing experience or technical knowledge',
                    'The AI-powered domain discovery system that scans real sales data to uncover domain opportunities buyers are already paying for',
                    'How to avoid costly mistakes with AI “Buy or Avoid” guidance and beginner safety filters',
                    'The simple system for estimating domain resale value using real comparable sales data from major marketplaces',
                    'How to register profitable domains instantly through integrated platforms like GoDaddy and Namecheap',
                    'The automated “For Sale” page generator that creates professional domain sales pages in seconds',
                    'How to list domains on high-traffic marketplaces where millions of buyers are already searching daily',
                    'The AI negotiation assistant that helps you decide whether to accept, counter, or reject offers confidently',
                    'How to manage your entire domain portfolio, renewal dates, estimated values, and listings from one dashboard',
                    'Includes step-by-step video training, PDF guides, and a beginner-friendly roadmap to help you land your first profitable domain flip with AIFlipDomains',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/UENv2iuBQw0',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/438197',
                'jv_page' => 'https://AIFlipDomains.com/partners',
            ],
            [
                'name' => 'GEO Optimizer Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the first WordPress plugin built specifically for AI search engines that helps your content become AI-readable, AI-citable, and AI-recommended across ChatGPT, Google AI Overviews, Claude, Perplexity, Gemini, and more.',
                'bullet_points' => [
                    'How to optimize your WordPress website for the new era of AI search and position your content to be cited by ChatGPT, Google AI, Perplexity, and other AI engines',
                    'The breakthrough “AI-Ready Writer” that restructures your content with FAQs, answer sections, schema, and authority signals in under 60 seconds',
                    'How to unlock AI crawler access so platforms like GPTBot, ClaudeBot, and Perplexity can discover and recommend your content',
                    'The simple system for adding powerful structured data and JSON-LD schema that AI engines trust for citations and rankings',
                    'How to generate an llms.txt file that helps prioritize your website for AI crawlers and boosts AI visibility',
                    'The built-in GEO Audit & Scoring system that grades every page for AI-readiness and identifies optimization opportunities instantly',
                    'How to future-proof your website for the shift from traditional SEO to AI-driven search traffic',
                    'Why AI referral traffic converts significantly better than traditional traffic sources and how to position yourself ahead of competitors',
                    'Works with virtually any WordPress theme and covers major AI platforms including ChatGPT, Claude, Gemini, Copilot, Perplexity, and Google AI Overviews',
                    'Perfect for bloggers, affiliate marketers, agencies, local businesses, and website owners looking to dominate the next wave of search with GEO Optimizer',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1185202127',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/437977',
                'jv_page' => 'https://geo.pixalab.ai/jv/',
            ],
            [
                'name' => 'Cinemation Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the breakthrough AI video platform that lets beginners create movies, documentaries, ads, educational videos, and cinematic content in any niche using powerful AI automation.',
                'bullet_points' => [
                    'How to create stunning full-length cinematic videos without cameras, actors, editing software, or production teams',
                    'The AI-powered storytelling system that generates consistent characters, scenes, and narratives automatically',
                    'How to create engaging videos for YouTube, marketing, education, documentaries, ads, and entertainment in virtually any niche',
                    'The breakthrough “character consistency” technology that keeps scenes and storytelling cohesive throughout your videos',
                    'How beginners are using AI to create professional-quality video content from simple ideas and prompts',
                    'The simple system for generating cinematic content in multiple visual styles tailored to different audiences and markets',
                    'How to create binge-worthy episodic content, short films, and storytelling videos without technical experience',
                    'The opportunity to produce high-quality content at scale without expensive equipment, freelancers, or production costs',
                    'Perfect for creators, marketers, educators, agencies, storytellers, and anyone looking to leverage the future of AI video creation',
                    'Learn how to turn ideas into cinematic experiences using Cinemation and create unlimited video projects faster than ever before',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1186242439',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/437175',
                'jv_page' => 'https://getcinemation.in/affiliate/',
            ],
            [
                'name' => 'SerpSling Ai Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the breakthrough AI search optimization platform that audits, fixes, and optimizes websites to appear inside major AI search engines automatically — helping beginners tap into one of the fastest-growing digital marketing opportunities.',
                'bullet_points' => [
                    'How to get websites ranked and recommended inside ChatGPT, Google AI, Claude, and other major AI search engines with just a few clicks',
                    'The simple AI-powered system that audits websites and reveals exactly why they are not showing up in AI search results',
                    'How the platform automatically fixes and optimizes AI search issues for you — no technical SEO skills required',
                    'The untapped “AI Search Optimization” opportunity businesses are already paying $500–$1,500 per month for',
                    'How to become one of the first agencies offering AI search ranking services while most marketers still don’t understand AEO',
                    'The done-for-you AI content generation system that creates AI-ready content optimized for modern AI search engines',
                    'How to leverage AI search traffic to attract higher-quality buyers and increase conversions for yourself or clients',
                    'The beginner-friendly deployment system that lets you activate ranking campaigns without manual work or complicated setups',
                    'Perfect for agencies, affiliate marketers, freelancers, bloggers, local businesses, and anyone looking to capitalize on the future of search',
                    'Learn how to position yourself ahead of the market using SERPSling and tap into the next wave of AI-driven traffic and visibility',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/IFbuWTHKjEE',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/435535',
                'jv_page' => 'https://launchspecial.vip/ssai/',
            ],
            [
                'name' => 'ClientForce Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the next generation AI sales platform that automates your entire sales workflow from lead generation to follow-up, AI voice calls, proposals, and booked meetings — without manual prospecting or chasing leads.',
                'bullet_points' => [
                    'How to launch self-running AI Sales Agents that continuously prospect and fill your pipeline automatically',
                    'The breakthrough system that follows up across Email, WhatsApp, SMS, LinkedIn, and AI Voice without manual work',
                    'How AI Voice agents qualify leads, handle objections, and help book meetings automatically',
                    'The automated proposal engine that generates personalized hosted proposals and tracks engagement in real time',
                    'How to manage every lead conversation from one unified inbox for maximum organization and efficiency',
                    'The simple system for creating a 24/7 AI-powered sales machine that keeps your pipeline active and revenue moving',
                    'How agencies, consultants, SaaS businesses, and service providers are using AI automation to increase booked calls and conversions',
                    'The advanced automation integrations that trigger calendars, webinars, CRM updates, Slack alerts, payment links, and more',
                    'How to leverage AI sales automation as a high-ticket service for clients and recurring monthly income opportunities',
                    'Discover the future of automated selling using ClientForce and learn how to scale sales without scaling manual effort',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/hIHhBPX-UkI',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/407804',
                'jv_page' => 'https://clientforce.io/launch/jv/',
            ],
            [
                'name' => 'Traffic Magnets Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the simple system for creating interactive calculators, widgets, and generators that attract targeted buyers from Google every month and turn that traffic into long-term recurring income.',
                'bullet_points' => [
                    'How to create simple interactive tools that attract targeted Google traffic automatically without going viral or posting daily',
                    'The “Traffic Magnet” strategy used by high-traffic websites to generate thousands of monthly visitors from specific buyer searches',
                    'How to build calculators, widgets, and generators that people naturally search for and engage with online',
                    'The beginner-friendly tool builder that lets you create and publish traffic-generating assets without coding or technical skills',
                    'How to instantly launch your tools using ready-made landing pages with one-click publishing',
                    'The built-in monetization system that helps turn traffic into income using AdSense, Ezoic, Monumetric, Mediavine, and more',
                    'How to create multiple small traffic assets that compound into large amounts of consistent organic traffic over time',
                    'The opportunity to rank for low-competition search terms where buyers are already actively searching',
                    'How to build passive traffic systems that continue bringing visitors and revenue month after month',
                    'Includes step-by-step traffic training showing how to turn simple online tools into scalable digital assets using Traffic Magnets',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/IFbuWTHKjEE',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/436159',
                'jv_page' => 'https://www.trafficmagnets.net/jvinvite',
            ],
            [
                'name' => 'Hooked Ai Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the new “Hook First” AI system that helps creators, marketers, affiliates, and business owners turn weak videos into attention-grabbing content designed to stop the scroll and keep people watching.',
                'bullet_points' => [
                    'The breakthrough “Hook First” strategy that makes short videos more engaging, clickable, and watchable from the very first seconds',
                    'Why most videos fail before the actual message even starts — and how to fix it immediately',
                    'How to create scroll-stopping hooks built around curiosity, suspense, authority, shock, and visual interruption',
                    'The simple system for turning weak promo videos into high-retention content that attracts more viewers and engagement',
                    'How beginners are using AI-powered hook videos to promote affiliate offers, products, coaching, ecommerce stores, local businesses, and more',
                    'How to create videos that feel more like entertaining content and less like obvious ads',
                    'The secret behind increasing watch time, clicks, leads, and conversions simply by improving your opening seconds',
                    'How to create compelling short-form content faster without constantly filming new videos from scratch',
                    'Perfect for YouTube Shorts, TikTok, Instagram Reels, Facebook videos, ads, and social media campaigns',
                    'Discover how HookedAI helps users create attention-grabbing videos designed for the modern short-form content era',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1176281878',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/435915',
                'jv_page' => 'https://hooked-ai.com/partners/',
            ],
            [
                'name' => 'Outrich Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the AI-powered outreach engine that finds leads, writes personalized emails, follows up automatically, and helps businesses generate more meetings, clients, and revenue on autopilot.',
                'bullet_points' => [
                    'How to build a predictable client acquisition system using AI-powered outreach automation',
                    'The simple system for finding and engaging high-value leads without manual prospecting or cold outreach stress',
                    'How AI writes personalized outreach emails and generates custom ice-breakers automatically',
                    'The breakthrough automation that analyzes prospect responses and intelligently replies for you in real time',
                    'How to scale email campaigns without hiring virtual assistants, sales teams, or outreach staff',
                    'The “humanized emailing” technology that improves inbox placement and helps reach more prospects effectively',
                    'How businesses are using AI automation to consistently book more meetings and grow revenue faster',
                    'The automated lead enrichment system that gathers prospect information and personalizes communication at scale',
                    'Perfect for agencies, freelancers, consultants, marketers, coaches, SaaS companies, and local businesses looking to generate more leads',
                    'Discover how Outrich helps automate prospecting, follow-ups, and sales conversations for scalable business growth',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1177278498',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/435937',
                'jv_page' => 'https://getoutrich.in/affiliate',
            ],
            [
                'name' => 'InstantlyClaw Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the breakthrough one-click AI agent deployment system that lets beginners launch fully functional AI teams capable of browsing websites, sending emails, creating documents, running workflows, and automating business operations in under 60 seconds.',
                'bullet_points' => [
                    'How to launch fully hosted AI agent systems with one click — no Docker, coding, VPS setup, or API configuration required',
                    'The breakthrough platform that deploys complete AI “companies” with CEOs, managers, and worker agents already configured for you',
                    'How to automate real business tasks like email sending, SEO research, web browsing, PDF creation, document generation, and workflow management',
                    'The simple system for deploying advanced AI agent platforms like OpenClaw, NanoClaw, PicoClaw, SuperAGI, and more without technical complexity',
                    'How to interact with your AI agents directly through Telegram, WhatsApp, Discord, Slack, and a web dashboard',
                    'The done-for-you cloud hosting setup that eliminates the need for servers, infrastructure, or ongoing technical maintenance',
                    'How businesses and marketers are leveraging AI agents to automate repetitive work and scale operations faster',
                    'The commercial rights opportunity that allows you to sell AI-generated outputs and services for additional income',
                    'Includes pre-installed skills, connected integrations, AI credits, and a fully operational AI workflow environment ready in minutes',
                    'Discover how InstantlyClaw makes advanced AI automation accessible to complete beginners with true one-click deployment',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/OGJ0_bnRvlE',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/436475',
                'jv_page' => 'https://InstantlyClaw.com/partners',
            ],
            [
                'name' => 'OpenClaw Cracked Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the new generation of AI systems that don’t just give suggestions — they execute. Watch AI build a business live by creating the offer, writing the copy, setting up payments, drafting emails, preparing ads, and launching the core assets automatically.',
                'bullet_points' => [
                    'Watch AI build a real online business live from scratch without slides, theory, or pre-recorded demos',
                    'See how AI can choose a business idea, create an offer, write the sales page, and prepare launch assets automatically',
                    'Discover the system that removes the need for developers, copywriters, designers, or large teams',
                    'How AI now connects tools, executes workflows, and launches businesses instead of simply giving advice',
                    'Watch AI generate follow-up emails, ads, and social media content designed to support a real launch',
                    'The simple framework for launching online faster without months of setup or technical overwhelm',
                    'How beginners are leveraging AI execution systems to start online businesses without prior experience',
                    'Learn how AI can remove the “blank page problem” and accelerate the hardest parts of building online',
                    'Why this shift from “AI assistants” to “AI execution systems” is changing how businesses are created',
                    'See what’s possible when AI handles the heavy lifting and execution live in real time using AI business execution system',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1174520054',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/435841',
                'jv_page' => 'https://jv.openclawcracked.com/',
            ],
            [
                'name' => 'LocalMator Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the new AI-powered system that helps local businesses monitor, manage, and respond to online reviews automatically — while positioning yourself to profit from one of the fastest-growing local business opportunities.',
                'bullet_points' => [
                    'How to start a high-income reputation management agency using AI automation instead of manual work',
                    'The simple system for helping local businesses improve reviews, reputation, and visibility without doing SEO or building backlinks',
                    'How AI monitors and responds to customer reviews automatically across major platforms',
                    'Why online reputation has become one of the biggest factors influencing local business leads, sales, and Google visibility',
                    'How ordinary people are landing clients paying $500–$1,500 per month for reputation management services',
                    'The growing opportunity created by changes to Google’s search results and the increasing importance of the Google Local Pack',
                    'How to help businesses attract more customers by improving trust, ratings, and customer engagement online',
                    'The beginner-friendly business model that doesn’t require coding, advanced marketing experience, or technical expertise',
                    'Perfect for freelancers, agencies, consultants, marketers, and anyone looking to start a scalable local business service',
                    'Discover how AI reputation management platform helps automate review monitoring, responses, and local reputation growth for businesses',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1175141536',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/435525',
                'jv_page' => 'https://jv.localmator.com/jv/',
            ],
            [
                'name' => 'CleverAi Studio Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the breakthrough AI platform that scans any website, learns your business automatically, and gives you access to 300+ premium AI tools for content creation, marketing, automation, design, SEO, video, voiceovers, and more.',
                'bullet_points' => [
                    'How to generate personalized, publish-ready AI content simply by entering a website URL — without endless prompting or manual setup',
                    'The breakthrough “URL-Powered AI Agent” that scans and trains itself on your business in seconds for highly tailored outputs',
                    'Access 300+ AI-powered tools for copywriting, videos, graphics, SEO, voiceovers, automation, chatbots, ads, and more from one dashboard',
                    'How to create scroll-stopping videos, Reels, Shorts, TikToks, graphics, logos, banners, and 4K visuals without design experience',
                    'The AI voiceover system that generates realistic narration with emotional tones for marketing and content creation',
                    'How to deploy AI chatbots that capture leads, answer customer questions, and help automate sales 24/7',
                    'The built-in SEO and marketing campaign engine that creates keyword clusters, ad copy, captions, hooks, and complete campaign plans automatically',
                    'Why AI trained on your actual business data creates faster, more accurate, and more conversion-focused outputs than generic AI tools',
                    'The commercial license opportunity that lets you sell AI-generated videos, graphics, campaigns, chatbots, and content services to clients',
                    'Discover how Clever AI Studio replaces dozens of expensive AI tools with one powerful AI business system',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1176243950',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/431727',
                'jv_page' => 'https://cleveraistudio.com/jv',
            ],
            [
                'name' => 'Prezent IQ Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the world’s first Agentic AI Presentation System that transforms ordinary slides into interactive AI-powered experiences capable of answering questions, capturing leads, guiding decisions, and helping businesses convert more customers 24/7.',
                'bullet_points' => [
                    'How to turn any idea, website, document, or PowerPoint into a talking AI presentation in minutes',
                    'The breakthrough “Interactive AI Presentation” technology that speaks, engages visitors, and responds to questions automatically',
                    'How to create presentations that capture leads, guide decisions, and increase conversions without manual interaction',
                    'The “Interactive Conversion Framework” designed to turn viewers into buyers through AI-powered engagement',
                    'How beginners are packaging and selling AI presentations as high-ticket services for $500–$2,000+ per client',
                    'The simple system for launching your own AI presentation agency without coding, developers, or design expertise',
                    'Discover 7 profitable niches actively looking for interactive presentation solutions right now',
                    'How AI-powered presentations can replace traditional static slides with dynamic, conversion-focused communication experiences',
                    'Perfect for agencies, consultants, freelancers, coaches, marketers, SaaS founders, and business owners looking to stand out',
                    'Learn how Prezent IQ helps create next-generation AI presentations that communicate, engage, and sell automatically',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/tUovMjS5A9Q',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/434763',
                'jv_page' => 'https://www.prezentiq.com/jv/',
            ],
            [
                'name' => 'ViralCharacters Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the AI-powered viral video studio that lets anyone create scroll-stopping short-form videos using talking babies, animals, celebrities, historical figures, parody characters, and custom avatars designed to boost views, engagement, and followers automatically.',
                'bullet_points' => [
                    'How to create viral-style short videos using AI-powered characters without filming yourself or learning video editing',
                    'The breakthrough “pattern interruption” video strategy that helps stop the scroll and increase watch time on TikTok, Instagram Reels, YouTube Shorts, and Facebook Reels',
                    'How to generate fully animated character videos complete with lip-sync, voiceovers, emotions, scenes, and storytelling automatically',
                    'Access 200+ viral-ready characters and templates including babies, gorillas, cats, politicians, celebrities, historical figures, and parody personalities',
                    'The AI-powered system that writes scripts, builds scenes, generates voices, and renders complete viral videos in minutes',
                    'How affiliate marketers, influencers, and theme page owners are using viral character content to attract traffic, followers, and engagement organically',
                    'The simple system for creating entertaining videos that feel less like ads and more like highly shareable social content',
                    'Perfect for content creators, agencies, meme pages, ecommerce brands, freelancers, marketers, and complete beginners',
                    'How to monetize viral short-form attention through affiliate marketing, brand deals, theme pages, social growth, and client services',
                    'Discover how ViralCharacters AI helps users create attention-grabbing short-form content designed for modern social media algorithms',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://viralcharactersai.com/Webinar-Replay.mp4',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/435113',
                'jv_page' => 'https://viralcharactersai.com/partners/',
            ],
            [
                'name' => 'GramGennies Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the first Meta-approved AI agent system that learns your business, creates viral content, grows real followers, handles DMs, and helps turn Instagram engagement into leads and sales automatically.',
                'bullet_points' => [
                    'How to grow targeted Instagram followers safely using Meta-approved AI agents instead of risky bots or automation tools',
                    'The breakthrough “Custom Knowledge AI” that learns your business, niche, offers, and tone to create highly personalized content automatically',
                    'How AI generates stage-based viral reels designed for reach, trust-building, and sales conversions',
                    'The automated DM and comment engagement system that responds in your voice, sends links, follows up, and moves conversations toward sales',
                    'How to create faceless Instagram content using AI-powered reels, posts, voiceovers, scripts, and custom visuals',
                    'The viral post engine that creates attention-grabbing X-style, Gmail-style, and Reddit-style content designed to increase engagement',
                    'How the Trend & Virality AI identifies rising trends, hashtags, and content formats in your niche before they peak',
                    'The built-in AI content planner and scheduler that maps out months of branded content automatically',
                    'Perfect for affiliate marketers, influencers, agencies, ecommerce brands, freelancers, local businesses, and beginners wanting to build Instagram income streams',
                    'Discover how GramGenies helps automate Instagram growth, engagement, content creation, and monetization without needing to show your face',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/rTPVpWrrf2k',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/432453',
                'jv_page' => 'https://gramgenies.com/jv/',
            ],
            [
                'name' => 'Synthesys Actors Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the breakthrough AI video marketing system that lets you create consistent AI actor videos for Reel ads, retargeting campaigns, long-form VSLs, product promotions, and complete high-converting sales funnels without filming yourself.',
                'bullet_points' => [
                    'How to create long-form AI Avatar Video Sales Letters designed to convert viewers into buyers automatically',
                    'The breakthrough “AI Actor Funnel” system that uses the same consistent AI presenter across your entire marketing funnel',
                    'How to create Reel ads, retargeting videos, product promos, and full VSLs without cameras, actors, or video production skills',
                    'Why long-form VSLs remain one of the most powerful conversion tools for affiliates, marketers, coaches, and businesses',
                    'The simple system for building trust and brand consistency through repeated exposure with the same AI actor',
                    'How beginners are creating professional marketing videos and scalable sales funnels without appearing on camera',
                    'The opportunity to produce complete AI-powered video marketing campaigns faster and at lower cost than traditional production',
                    'Perfect for affiliate marketers, agencies, ecommerce brands, coaches, consultants, SaaS companies, and digital product sellers',
                    'How to combine short-form attention videos with long-form conversion videos to maximize leads and sales',
                    'Discover how Synthesys Actors helps create AI-powered video funnels that attract attention, build trust, and drive conversions automatically',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1169565239',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/434841',
                'jv_page' => 'https://synthesys.ai/jv-sa',
            ],
            [
                'name' => 'CogneX Ai Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the all-in-one AI platform that combines content creation, video generation, voiceovers, websites, automation, design, podcasts, and marketing tools into one powerful system designed to help beginners launch scalable AI-powered businesses.',
                'bullet_points' => [
                    'How to replace expensive tools like ChatGPT, Canva, MidJourney, Jasper, ElevenLabs, InVideo, ClickFunnels, and more with one AI platform',
                    'The breakthrough all-in-one AI system that creates content, videos, graphics, podcasts, websites, automation workflows, and marketing assets from a single dashboard',
                    'How to use AI to start multiple online businesses without hiring freelancers, agencies, or technical teams',
                    'The “AI Business Blueprint” showing how beginners are building profitable side hustles and agency-style services using AI automation',
                    'How to create and sell AI-generated content, voiceovers, websites, videos, graphics, automation services, and more for clients',
                    'The done-for-you business ideas and niche opportunities that can be launched quickly using built-in AI tools',
                    'How marketers, freelancers, agencies, and entrepreneurs are reducing software costs while increasing productivity and scalability',
                    'The beginner-friendly approach that removes the need for coding, design experience, or advanced technical knowledge',
                    'How to leverage AI automation to complete client projects faster and generate more income opportunities online',
                    'Discover how CogneX AI helps users replace dozens of AI subscriptions and build scalable AI-powered businesses from one dashboard',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/pjP0g2glTRk',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/431935',
                'jv_page' => 'https://www.cognexai.co/jv/',
            ],
            [
                'name' => 'Reddify Ai Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the AI-powered Reddit automation system that creates viral-style posts, finds targeted subreddits, follows up automatically, captures leads, and monetizes traffic with high-ticket offers on autopilot.',
                'bullet_points' => [
                    'How to leverage Reddit for consistent free traffic without running paid ads or posting manually every day',
                    'The AI-powered content engine that creates Reddit-style posts trained on viral threads and proven engagement patterns',
                    'How to automatically find high-activity subreddits filled with targeted buyers interested in your niche',
                    'The automated Reddit posting system that keeps campaigns running consistently without constant manual work',
                    'How Auto-DM follow-up turns Reddit engagement into leads, conversations, and potential sales automatically',
                    'The built-in lead capture and tracking system that helps collect emails, monitor engagement, and measure campaign performance in real time',
                    'How to monetize Reddit traffic with done-for-you high-ticket affiliate offers and proven sales frameworks',
                    'The AI training system that lets you create niche-specific AI agents modeled after successful Reddit content styles',
                    'Perfect for affiliate marketers, freelancers, agencies, content creators, and entrepreneurs looking for alternative traffic sources',
                    'Discover how ReddifyAI helps automate Reddit traffic generation, lead capture, and high-ticket monetization from one dashboard',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/pjP0g2glTRk',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/432379',
                'jv_page' => 'https://www.reddifyai.net/affiliate-invite',
            ],
            [
                'name' => 'Micro Content Agency Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the AI-powered agency platform that reads client websites, extracts brand voice automatically, creates 30-day video content systems, generates scripts, produces videos, and publishes content — all from one dashboard.',
                'bullet_points' => [
                    'How to turn any client website into a complete branded video content system in as little as 10 minutes',
                    'The breakthrough AI onboarding system that eliminates weeks of discovery calls, questionnaires, and manual client research',
                    'How AI extracts brand voice, target audience, messaging style, and unique selling points automatically from any business website',
                    'The automated 30-day content calendar engine that plans strategic video topics and posting schedules for clients',
                    'How GPT-5-powered AI generates professional scripts written in the client’s unique tone and communication style',
                    'The built-in AI video studio that creates professional branded videos with voiceovers, animations, transitions, captions, and visual styles automatically',
                    'How to publish directly to YouTube or export videos for TikTok, Instagram, Facebook, and other social platforms',
                    'The simple system for starting a recurring-revenue AI video agency charging $500–$1,000+ monthly retainers',
                    'Perfect for freelancers, agencies, marketers, consultants, and entrepreneurs looking to scale content creation without hiring teams',
                    'Discover how Micro Content Agency helps automate client onboarding, content planning, script creation, video production, and publishing from one dashboard',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/gHZNHY-g2t4',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/432909',
                'jv_page' => 'https://gomicrocontent.com/jv',
            ],
            [
                'name' => 'Imimic Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the AI-powered platform that lets anyone build hyper-realistic virtual influencers capable of creating videos, posting content, attracting followers, and monetizing social media across multiple niches on autopilot.',
                'bullet_points' => [
                    'How to create hyper-realistic AI influencers that look, speak, and behave like real creators without filming yourself',
                    'The AI-powered content engine that generates viral Reels, Shorts, talking-head videos, UGC ads, and trending content automatically',
                    'How to launch faceless influencer brands that stay active daily and grow audiences consistently on autopilot',
                    'The breakthrough AI lip-sync and voice technology that creates natural, expressive influencer videos in minutes',
                    'How to customize personality, tone, voice, interests, and branding for fully unique digital influencer identities',
                    'The simple system for placing AI influencers in luxury locations, events, travel scenes, and premium environments with one click',
                    'How affiliate marketers, creators, agencies, and entrepreneurs are monetizing AI influencers through sponsorships, brand deals, products, and promotions',
                    'The opportunity to manage multiple niche influencer accounts simultaneously without hiring creators or production teams',
                    'Perfect for content creators, agencies, ecommerce brands, affiliate marketers, freelancers, and beginners wanting to build faceless social media businesses',
                    'Discover how Imimic helps automate influencer creation, content production, audience growth, and monetization at scale',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1167099588',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/433913',
                'jv_page' => 'https://getimimic.in/affiliate',
            ],
            [
                'name' => 'AiPodVids Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the world’s first AI podcast clip engine that transforms simple keywords into addictive short-form podcast videos designed to grab attention, increase engagement, and generate traffic across YouTube, TikTok, Instagram, Facebook, LinkedIn, and more.',
                'bullet_points' => [
                    'How to create scroll-stopping podcast-style videos using AI without recording yourself or learning video editing',
                    'The simple 3-step system that turns one keyword into a complete viral-ready podcast clip automatically',
                    'How AI generates the dialogue, pacing, visuals, branding, storytelling flow, and calls-to-action for you',
                    'The breakthrough “Podcast Clip” content style designed to increase watch time, trust, engagement, and social media reach',
                    'How to consistently create viral short-form content without camera pressure, scripting, or technical skills',
                    'The simple system for generating attention-grabbing clips for YouTube Shorts, TikTok, Instagram Reels, Facebook, and LinkedIn',
                    'How marketers, creators, affiliate promoters, coaches, and entrepreneurs are using AI podcast clips to drive traffic and sales',
                    'The automated content creation process that removes the stress of figuring out what content to post daily',
                    'Perfect for faceless creators, agencies, social media marketers, affiliate marketers, freelancers, and beginners',
                    'Discover how AI PodVids helps users create viral podcast-style content that attracts followers, builds authority, and drives conversions automatically',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1166047132',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/433075',
                'jv_page' => 'https://www.aipodvids.com/jv',
            ],
            [
                'name' => 'AiDirectors Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the 1-click AI business builder that transforms any product image, affiliate offer, or website link into realistic human-style videos designed to generate traffic, grow faceless channels, and drive affiliate commissions automatically.',
                'bullet_points' => [
                    'How to create scroll-stopping AI UGC videos for TikTok, Instagram Reels, and YouTube Shorts without recording yourself',
                    'The breakthrough 1-click AI system that writes scripts, creates spokesperson videos, adds voiceovers, builds scenes, and generates captions automatically',
                    'How to turn affiliate offers, local business services, or products into ready-to-post promotional videos in minutes',
                    'The simple system for building faceless content channels that attract views, clicks, and sales consistently',
                    'How AI replaces expensive video agencies, copywriters, editors, voice actors, and creative teams from one dashboard',
                    'The built-in AI traffic system that helps distribute and promote videos across major social media platforms',
                    'How beginners are using AI-generated videos to earn affiliate commissions and launch online businesses without followers or experience',
                    'The opportunity to offer AI video ad creation services for local businesses and clients as a scalable agency model',
                    'Perfect for affiliate marketers, content creators, agencies, freelancers, ecommerce sellers, and anyone wanting to monetize short-form video traffic',
                    'Discover how AIDirectors helps automate video creation, promotion, and monetization with a true 1-click AI workflow',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/uZbAoOA58js',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/432693',
                'jv_page' => 'https://www.AIDirectors.io/partners',
            ],
            [
                'name' => 'Creativo ai 2.0 Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'Discover the AI-powered image editing platform that helps ecommerce sellers, marketers, and creators generate, enhance, upscale, and transform professional-quality visuals with one click.',
                'bullet_points' => [
                    'How to create professional-grade product images, marketing visuals, and social media graphics without Photoshop or advanced editing skills',
                    'The AI-powered image engine that generates and enhances thousands of visuals automatically with lightning-fast processing',
                    'How to remove backgrounds, upscale images to HD quality, and create polished product photos in seconds',
                    'The simple system for generating multiple image variations optimized for ecommerce stores, ads, websites, and social media platforms',
                    'How AI-powered scene generation creates professional product photoshoots without expensive cameras or studio setups',
                    'The GPT-4 image enhancement technology that improves prompts, visuals, and overall image quality automatically',
                    'How marketers and ecommerce brands are creating high-converting visual content faster and at lower cost using AI',
                    'The beginner-friendly platform that supports multiple aspect ratios for TikTok, Instagram Reels, YouTube, ads, and websites',
                    'Perfect for ecommerce sellers, agencies, affiliate marketers, content creators, freelancers, and digital businesses',
                    'Discover how Creativio AI 2.0 helps users create professional visuals, optimize product images, and elevate their digital presence with AI automation',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1160264284',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/432539',
                'jv_page' => 'https://creativio.io/partners',
            ],
            [
                'name' => 'InstaEngine Ai Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Launch Viral Faceless Instagram Pages That Grow Followers, Drive Traffic & Generate Income Automatically — Without Filming or Editing',
                'bullet_points' => [
                    'How to create viral Instagram Reels automatically without filming, scripting, editing, or showing your face',
                    'The breakthrough Instagram automation system that auto-posts, auto-comments, auto-replies, and grows pages around the clock',
                    'The “Insta Automation Loop” strategy designed to build followers, engagement, traffic, and sales on autopilot',
                    'How beginners are launching faceless Instagram businesses without prior social media experience',
                    'The simple system for running multiple faceless Instagram pages in profitable niches using AI automation',
                    'Discover 5 proven Instagram niches designed for fast growth, engagement, and monetization',
                    'How affiliate marketers, freelancers, and agencies are using AI-powered Instagram pages to drive traffic and generate commissions',
                    'The opportunity to create and sell done-for-you Instagram pages for clients and charge premium monthly fees',
                    'Perfect for creators, agencies, ecommerce sellers, affiliate marketers, local businesses, and entrepreneurs wanting scalable social media growth',
                    'Discover how Insta Engine AI helps automate content creation, posting, engagement, and growth for faceless Instagram businesses',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/IVHRYCwxEyY',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/432753',
                'jv_page' => 'https://www.instaengineai.com/jv/',
            ],
            [
                'name' => 'Showcase Ai offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Create Studio-Quality AI Marketing Videos From Simple Photos — Without Filming, Actors, or Editing Skills',
                'bullet_points' => [
                    'How to turn simple product images and photos into professional-quality marketing videos using AI automation',
                    'The breakthrough AI avatar technology that creates realistic talking spokesperson videos from a single image',
                    'How to create cinematic product showcase videos without cameras, actors, production teams, or expensive editing software',
                    'The AI-powered system that automatically generates scripts, voiceovers, avatars, visuals, and final video outputs for you',
                    'How ecommerce brands, marketers, and creators are producing high-converting ad videos faster and at lower cost using AI',
                    'The simple system for creating virtual try-on videos and fashion showcases for clothing, accessories, and ecommerce products',
                    'How beginners can create attention-grabbing promotional videos without technical experience or creative skills',
                    'Perfect for ecommerce stores, agencies, affiliate marketers, social media creators, advertisers, and online businesses',
                    'The opportunity to offer AI-powered video creation services for brands and clients as a scalable business model',
                    'Discover how Showcase AI helps automate video production, avatar creation, and product marketing from one simple dashboard',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1161533234',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/432091',
                'jv_page' => 'https://showcaseai.app/jv',
            ],
            [
                'name' => 'Helpira Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Launch an AI-Powered Helpdesk Business That Automates Customer Support & Generates Recurring Income',
                'bullet_points' => [
                    'How to automate customer support using AI without hiring large support teams or paying expensive monthly software fees',
                    'The AI-powered helpdesk system that handles customer questions, support tickets, complaints, and order issues automatically',
                    'How to create a professional support platform for ecommerce stores, agencies, clinics, coaches, startups, and local businesses',
                    'The centralized dashboard that tracks customer conversations, issues, orders, feedback, and ticket statuses in one place',
                    'How to leverage detailed analytics and performance reports to improve response times, customer satisfaction, and support efficiency',
                    'The white-label and agency opportunity that allows you to rebrand the platform as your own SaaS business',
                    'How freelancers, marketers, and consultants are selling AI-powered support systems to clients for recurring monthly income',
                    'The built-in multilingual support system that helps businesses serve customers across multiple languages and regions',
                    'Perfect for agencies, SaaS resellers, ecommerce brands, local businesses, BPO teams, startups, freelancers, and entrepreneurs',
                    'Discover how Helpira helps automate support operations while creating scalable business and SaaS opportunities',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1147744569',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/429921',
                'jv_page' => 'https://gethelpira.in/affiliate',
            ],
            [
                'name' => 'SuperClips Ai offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Create Professional AI Videos From Simple Ideas — Without Cameras, Actors, or Editing Skills',
                'bullet_points' => [
                    'How to turn simple ideas into ready-to-publish AI videos in seconds without filming or editing manually',
                    'The breakthrough “Audio-Sync” AI engine that combines cinematic visuals, emotional voiceovers, and background music automatically',
                    'How to create high-converting short-form videos for TikTok, YouTube Shorts, Instagram Reels, Facebook ads, ecommerce, and faceless channels',
                    'The AI-powered scriptwriter, cinematographer, and sound engine that handles the entire creative process for you',
                    'The Character Consistency system that keeps the same AI actor or brand identity across multiple videos automatically',
                    'How to create product commercials, social media clips, promotional ads, and branded content using uploaded product images',
                    'The conversational AI Copilot that lets you create and refine multiple videos simply by chatting in plain English',
                    'How to generate AI voiceovers, multi-language videos, text-on-screen effects, music tracks, and cinematic visuals from one workflow',
                    'The built-in agency blueprint showing how to monetize AI video creation through client services, faceless channels, digital products, and high-ticket funnels',
                    'Discover how SuperclipsAI helps users create professional-quality videos, automate production, and build scalable content businesses with AI',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1148327633',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/429889',
                'jv_page' => 'https://superclipsai.com/partners/',
            ],
            [
                'name' => 'TokPrime Ai Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Create Viral TikTok Videos With AI That Generate Massive Views, Followers & Traffic — Without Recording Yourself',
                'bullet_points' => [
                    'How to create viral TikTok videos automatically without filming, editing, or showing your face',
                    'The AI-powered trend engine that discovers hot topics, viral videos, and trending hashtags before they peak',
                    'How to generate complete TikTok videos from simple keywords including scripts, visuals, captions, and realistic AI voiceovers',
                    'The competitor spy tools that reveal winning content ideas, trending accounts, and viral posting strategies in your niche',
                    'How to ethically remix and republish successful viral-style content to increase reach and engagement faster',
                    'The automated scheduling system that posts content consistently and keeps your TikTok active while you sleep',
                    'The built-in analytics dashboard that tracks views, followers, engagement, and niche performance trends in real time',
                    'How beginners are growing faceless TikTok channels and driving massive traffic using AI-powered content automation',
                    'The advanced timeline video editor that allows drag-and-drop editing, scene creation, captions, effects, and multi-track production',
                    'Discover how AI TikTok automation platform helps automate viral content creation, growth, posting, and traffic generation for modern short-form platforms',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/9eS5UD3MMdU',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/430877',
                'jv_page' => 'https://tokprimeai.com/jv',
            ],
            [
                'name' => 'OmniMint Ai offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Build & Sell Your Own Branded AI Software Business — Across Apple, Android, Desktop & Web — Without Coding',
                'bullet_points' => [
                    'How to launch your own fully branded AI software business without coding, developers, or expensive app creation costs',
                    'The breakthrough multi-platform deployment system that publishes apps across Apple, Android, Desktop, and Web from one dashboard',
                    'Access 1,000+ pre-built AI modules covering content creation, automation, marketing, business tools, image generation, and more',
                    'How to create revenue-ready SaaS applications using simple drag-and-drop configuration instead of complex programming',
                    'The complete white-label system that lets you add your own logo, branding, domain, pricing, and business identity',
                    'How to monetize AI apps using subscriptions, one-time payments, credit systems, and recurring revenue models',
                    'The built-in Stripe and PayPal integrations that allow you to accept payments and manage customers automatically',
                    'How beginners are using AI software businesses to generate recurring income through direct outreach, client services, and SaaS models',
                    'The multi-app business model that lets you launch and test multiple AI software brands across different niches simultaneously',
                    'Discover how OmniMint AI Suite helps users create scalable AI SaaS businesses without technical barriers or coding experience',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/9eS5UD3MMdU',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/431377',
                'jv_page' => 'https://getomnimint.com/jv',
            ],
            [
                'name' => 'Cohortia offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Build AI-Powered Communities That Grow, Engage & Monetize Automatically — Without Managing Everything Manually',
                'bullet_points' => [
                    'How to launch highly engaging online communities without constantly posting, moderating, or manually managing conversations',
                    'The AI-powered content engine that automatically generates posts, updates, discussions, and engagement inside your community',
                    'How virtual AI members create social proof, interact like real users, and keep communities active 24/7',
                    'The automated AI moderation system that enforces rules, filters content, and keeps discussions aligned with your standards',
                    'How to monetize communities through memberships, premium content, courses, paid events, and exclusive access tiers',
                    'The all-in-one platform featuring posts, chats, events, activity feeds, profiles, and mobile-friendly experiences from one dashboard',
                    'How creators, coaches, brands, educators, and entrepreneurs are building thriving communities without daily manual effort',
                    'The SEO-friendly infrastructure designed to attract organic traffic and help communities grow faster online',
                    'Perfect for creators, agencies, educators, coaches, course sellers, niche communities, brands, and membership businesses',
                    'Discover how Cohortia helps automate engagement, moderation, growth, and monetization for scalable online communities',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1157883028',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/431461',
                'jv_page' => 'https://getcohortia.in/affiliate/',
            ],
            [
                'name' => 'OminiSitesAi offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Build AI-Powered Websites That Market Themselves Automatically — Without Coding or Technical Skills',
                'bullet_points' => [
                    'How to build intelligent AI-powered websites that automatically create content, generate traffic, and market themselves continuously',
                    'The breakthrough “Custom Knowledge AI” technology that trains websites to understand your business, products, and offers deeply instead of generating generic content',
                    'How to instantly create affiliate sites, ecommerce stores, local business websites, funnels, blogs, and info-product sites from simple keywords',
                    'The built-in “Self-Marketing Agent Mode” that automatically generates content and runs marketing campaigns for your site',
                    'Access thousands of professionally designed templates with drag-and-drop customization and no coding required',
                    'The advanced AI content engine that creates long-form, expert-style articles designed to compete with top-ranking websites',
                    'How to use AI Lead Finder technology to discover businesses needing better websites and generate audit reports that help land clients',
                    'The simple system for creating and selling AI-powered self-growing websites as a high-ticket service business',
                    'Perfect for affiliate marketers, agencies, freelancers, ecommerce brands, local businesses, and entrepreneurs looking to automate online growth',
                    'Discover how OmniSitesAI helps users create intelligent websites that build traffic, leads, and revenue automatically 24/7',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1157883028',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/424523',
                'jv_page' => 'https://omnisites.io/jv/',
            ],
            [
                'name' => 'ChannelBuilder Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Build & Monetize Faceless YouTube Channels Using AI — Without Recording Videos, Hiring Editors, or Spending Months Learning Tech',
                'bullet_points' => [
                    'How to create high-quality faceless videos in minutes using AI automation',
                    'The exact method to build content that attracts views, traffic, and income on autopilot',
                    'How to turn simple ideas into engaging, story-driven videos without editing skills',
                    'The “multi-platform leverage” strategy to publish once and get traffic from YouTube, TikTok, and Instagram at the same time',
                    'How to use AI to find trending topics, write scripts, and optimize videos for SEO',
                    'How beginners are using this to start faceless content businesses and sell video services to clients',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/VwLwGUeO04w',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/417531',
                'jv_page' => 'https://channelbuilderai.com/jv',
            ],
            [
                'name' => 'Writix Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Create High-Converting Content & Automate Your Business Using AI — Without Writing, Prompting, or Wasting Hours',
                'bullet_points' => [
                    'How to create blogs, ads, emails, and social content in minutes using AI',
                    'The simple system to automate your business tasks without technical skills',
                    'How to eliminate “prompt struggles” and get results instantly',
                    'How to use AI workflows to save hours of work daily',

                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://www.youtube.com/watch?v=xGQyPjSazP0',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/417489',
                'jv_page' => 'https://getwritix.in/affiliate',
            ],
            [
                'name' => 'GoBeaver Ai Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Create High-Converting Ads, Emails & Content in Minutes Using AI — Without Writing From Scratch',
                'bullet_points' => [
                    'How to create scroll-stopping ads, emails, and social content in minutes',
                    'The simple method to find and model proven winning ads in your niche',
                    'How to eliminate guesswork and launch campaigns that convert faster',
                    'How to automate your marketing without hiring copywriters or agencies',

                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/eMmc2Tnlo9A',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/418161',
                'jv_page' => 'https://pages.gobeaver.ai/gb-jv',
            ],
            [
                'name' => 'Qroq Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Turn Simple QR Codes Into Traffic, Leads & Sales — Without Websites, Tech Skills, or Paid Ads',
                'bullet_points' => [
                    'How to create smart, branded QR codes that actually get scanned',
                    'How to turn QR codes into lead generation machines',
                    'How to build QR-powered landing pages without hosting or coding',
                    'How to track scans, understand your audience, and optimize campaigns',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BECOME OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1087398273',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/418321',
                'jv_page' => 'https://getqroq.in/affiliate',
            ],
            [
                'name' => 'AiSeller Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Turn Any Product Link Into a Full Marketing Campaign Using AI — In Minutes (No Writing, No Tech Skills)',
                'bullet_points' => [
                    'How to turn any product or offer into a complete marketing campaign instantly',
                    'How to generate videos, pages, emails, and content without writing anything',
                    'The simple system to launch campaigns faster and start getting results quickly',
                    'How to create lead magnets, blogs, and ads on autopilot',

                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BECOME OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/d-Z8S83uDik',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/418589',
                'jv_page' => 'https://aisellers.io/partners/',
            ],
            [
                'name' => 'KidBooks Ai Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Create & Profit From AI-Powered Talking Kids Books — Without Writing, Designing, or Hiring Anyone',
                'bullet_points' => [
                    'How to create interactive, talking kids books in minutes using AI — without writing a single word',
                    'The exact method we used to build a $3K/week passive income stream with AI-generated books',
                    'How to turn a keyword, URL, or simple idea into a fully-finished, story-driven kids book with narration',
                    'The secret to creating highly engaging books with animations, voiceovers, and interactive elements that stand out',
                    'How to publish books that attract attention, build credibility, and actually SELL',
                    'The “multi-market strategy” to sell your books to parents, schools, agencies, and freelancers',

                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/ygLMWQaCcug',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/416939',
                'jv_page' => 'https://www.kidsbookai.com/jv/',
            ],
            [
                'name' => 'AffiliateReel Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Build & Profit From AI-Powered Affiliate Websites — Without Writing Content, Creating Videos, or Driving Traffic Manually',
                'bullet_points' => [
                    'How to create fully functional affiliate websites in under 60 seconds using AI automation',
                    'The exact system that generates built-in buyer traffic automatically (no SEO or ads required)',
                    'How to produce AI-generated product review videos, articles, and comparison content without writing or recording anything',
                    'The secret to building high-converting bonus pages that increase your affiliate commissions',
                    'How to turn any niche, keyword, or product into a ready-to-profit affiliate asset with one click',
                    'The “set-and-profit” strategy to earn commissions by recommending products like top affiliate marketers',

                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/zoICOLa8yeI',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/419893',
                'jv_page' => 'https://affiliatereel.io/partners/',
            ],
            [
                'name' => 'Articalize Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Create High-Ranking, Traffic-Generating Articles With AI — Without Writing From Scratch or Running Ads',
                'bullet_points' => [
                    'How to turn YouTube videos, social posts, URLs, and threads into high-quality articles in minutes',
                    'The exact system to create SEO-optimized content that ranks and drives free traffic on autopilot',
                    'How to generate product reviews, listicles, and comparison articles that convert into sales',
                    'The secret to keeping your content “fresh” using real-time social media data (instead of outdated AI content)',
                    'How to automatically create AI-generated images and visuals that boost engagement and rankings',
                    'The “publish-on-autopilot” method to post directly to WordPress or Tumblr without manual work',
                    'How to use AI to rewrite, expand, summarize, and humanize content that passes AI detection tools',
                    'How beginners are using this to dominate any niche and scale content creation without effort',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1095102523',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/419237',
                'jv_page' => 'https://getarticalize.in/affiliate',
            ],
            [
                'name' => 'OhSoReal Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Create Ultra-Realistic UGC Videos With AI — Without Filming, Hiring Creators, or Showing Your Face',
                'bullet_points' => [
                    'How to create ultra-realistic UGC videos in seconds just by typing what you want',
                    'The exact method to generate high-converting videos without filming, actors, or editing skills',
                    'How to customize your avatar’s look, voice, and environment for any niche or product',
                    'The secret to creating UGC-style content that boosts engagement and conversions on social media',
                    'How to produce videos in 100+ languages to reach global audiences instantly',
                    'The “done-for-you persona” strategy using prebuilt avatars you can reuse and scale',
                    'How to create and sell UGC videos as a service to businesses for profit',
                    'How beginners are using this to launch content businesses without equipment, experience, or upfront costs',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1093760700',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/418131',
                'jv_page' => 'https://ohsoreal.ai/jv',
            ],
            [
                'name' => 'AiGency Valet Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Get $500–$1,000 Clients On Autopilot Using AI — Without Cold Calling, Outreach, or Sales Experience',
                'bullet_points' => [
                    'How to use AI to find high-quality clients in any niche or location automatically',
                    'The exact system that researches your prospects, identifies their problems, and positions you as the solution',
                    'How AI can uncover decision-makers, direct emails, and real contact details (no more gatekeepers)',
                    'The secret to sending hyper-personalized outreach that gets replies and converts into paying clients',
                    'How to land $500–$1,000 clients consistently without cold calling or manual prospecting',
                    'The “self-training AI” method that learns about your prospects and improves results over time',
                    'How to automate client acquisition, outreach, and closing — all on autopilot',
                    'How beginners are using this to start or scale agencies, freelancing businesses, and side hustles fast',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/AdEovk5DfNo',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/419307',
                'jv_page' => 'https://launchspecial.vip/ai-valet-jv/',
            ],
            [
                'name' => 'AiWrappers Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Create & Sell Your Own AI Apps (SaaS) — Without Coding, Tech Skills, or Managing Servers',
                'bullet_points' => [
                    'How to create real, monetizable AI apps in seconds just by typing or speaking your idea',
                    'The exact system to build AI-powered tools (text, image, video, voice) without coding or setup',
                    'How to launch fully hosted apps with built-in monetization (Stripe ready)',
                    'The secret to creating whitelabel AI tools you can brand and sell as your own SaaS business',
                    'How to use 100+ templates to instantly build apps like ad generators, avatars, planners, and more',
                    'The “multi-language advantage” to build once and sell globally in 50+ languages',
                    'How to switch between top AI models (GPT, Claude, Gemini, Veo, etc.) with one click',
                    'How beginners are using this to launch AI businesses, sell tools to clients, and scale recurring income',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1101009107',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/419593',
                'jv_page' => 'https://aiwrappers.dev/partners',
            ],
            [
                'name' => 'Vidatia Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Create High-Quality AI Videos In One Click — Without Filming, Editing, or Technical Skills',
                'bullet_points' => [
                    'How to create professional-quality videos in one click using AI automation',
                    'The exact system to generate scripts, visuals, voiceovers, and captions automatically',
                    'How to produce faceless videos for YouTube, Shorts, Reels, and TikTok without recording anything',
                    'The secret to creating attention-grabbing videos in any style (realistic, cartoon, 3D, anime, etc.)',
                    'How to use AI to create human-like voiceovers with emotions, accents, and even voice cloning',
                    'The “multi-platform leverage” strategy to publish once and get traffic from multiple platforms',
                    'How to create short-form viral content and long-form YouTube videos for monetization',
                    'How beginners are using this to make money from YouTube, ads, and selling video services to clients',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1103135400',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/420441',
                'jv_page' => 'https://getvidatia.in/affiliate',
            ],
            [
                'name' => 'ViralReel Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Build & Monetize Faceless Instagram & TikTok Pages With AI — Without Showing Your Face, Creating Content, or Growing Followers Manually',
                'bullet_points' => [
                    'How to create viral faceless videos, image posts, captions & hashtags in minutes using AI',
                    'The exact system to grow real followers automatically with a built-in Followers Generator',
                    'How to build fully automated Instagram & TikTok pages that post content for you daily',
                    'The “hands-free content” strategy to schedule weeks or months of posts in advance',
                    'How to turn your page into a money-making asset using affiliate links, offers, or lead generation',
                    'The secret to creating high-engagement content using trending topics, quotes & AI voice',
                    'How beginners are using this to generate traffic, leads, and commissions without showing their face',
                    'How to go from zero to a growing, monetized faceless account without tech skills or experience',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/3Y5vs9iC1DE',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/421149',
                'jv_page' => 'https://viralreel.io/partners/',
            ],
            [
                'name' => 'Ai Talker Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Design Any Voice You Want with AI… Generate Studio-Quality Voiceovers in Seconds (No Recording Needed)',
                'bullet_points' => [
                    'How to design your own unique voice from scratch instead of using boring preset voices',
                    'The secret to creating human-like voiceovers with real emotions like excitement, urgency, and storytelling tone',
                    'How to turn simple text into professional voiceovers for ads, YouTube, podcasts, and sales videos',
                    'How to match any voice to a brand using AI Brand Architect technology',
                    'How to create voices based on personality types (CEO, teacher, storyteller, salesperson, etc.)',
                    'How to generate voiceovers for different use cases like audiobooks, e-learning, and explainer videos',
                    'How to produce high-quality audio using built-in enhancement and mixing tools',
                    'How to create scripts and voiceovers together using AI-powered transcription and VSL generation',

                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1186724898',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/437269',
                'jv_page' => 'https://aitalker.io/jv',
            ],
            [
                'name' => 'Local Ai Fleet Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Deploy AI “Employees” for Local Businesses in 8 Minutes… and Get Paid $300–$800/Month Per Client',
                'bullet_points' => [
                    'How to deploy AI Employees that respond to leads instantly via SMS',
                    'The simple method to use smart QR codes to capture and convert local leads automatically',
                    'How to help businesses stop losing $2,400/month from poor follow-up',
                    'How to create automated SMS conversations that qualify leads and book appointments 24/7',
                    'How to build a recurring income stream charging $300–$800/month per client',
                    'How to find and target local businesses like dentists, gyms, salons, and auto shops',
                    'How to use AI to score, qualify, and nurture leads without manual effort',
                    'How to create high-converting landing pages and funnels without design skills',
                    'How to run a full AI-powered agency with built-in tools, templates, and automation',
                    'How to manage clients, track performance, and collect payments automatically',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/H835HVSZcck',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/421657',
                'jv_page' => 'http://getlocalaifleet.com/jv',
            ],
            [
                'name' => 'MagicPods Ai Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Turn Any Idea, URL, or PDF Into a Viral AI Podcast Video in Minutes (No Camera, Mic, or Editing)',
                'bullet_points' => [
                    'How to turn any text, URL, PDF, or idea into a full podcast video automatically',
                    'How to create realistic two-host conversations without recording anything',
                    'The step-by-step process to go from script → voices → visuals → captions in minutes',
                    'How to generate engaging videos ready for YouTube, TikTok, Reels, and Shorts',
                    'How to add animated captions and 1-click B-roll visuals effortlessly',
                    'How to create content in multiple formats (vertical, square, landscape) for maximum reach',
                    'How to produce high-quality podcast-style videos without editing skills',
                    'How beginners are using this to build content channels and grow audiences fast',
                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://vimeo.com/1111460086',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/421671',
                'jv_page' => 'https://magicpodsai.com/partners/',
            ],
            [
                'name' => 'eBook Valet Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'The #1 Way To Create, Design and Publish Profit-Producing eBooks (And Audio Books) - For ANY Niche - In A Matter of MINUTES!',
                'bullet_points' => [
                    'Create & Publish Complete eBooks in Minutes — No Writing or Design Needed. Just enter a topic and let AI handle everything from content to cover, layout, and formatting.',
                    'Done-For-You OR Fully Custom — Perfect for Beginners & Pros Use push-button automation for instant results or switch to manual mode for full creative control.',
                    'Built-In Monetization — Turn Every eBook Into a Lead & Sales Machine. Add clickable links, offers, and calls-to-action that generate income on autopilot.',
                    'Tap Into Global Markets With 95+ Languages + Instant Audiobooks. Create multilingual eBooks and convert them into audiobooks in seconds for wider reach and passive traffic.',

                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/CJSSBcBfBuY',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/425957',
                'jv_page' => 'https://launchspecial.vip/ev-jv/',
            ],
            [
                'name' => 'Buzz Agent Offer',
                'optin_heading' => '🔥100% FREE TRAINING REVEALS:',
                'optin_intro' => 'How To Grow Massive Social Media Accounts & Promote Affiliate Offers on Autopilot Using AI Agents — Without Creating Content Manually',
                'bullet_points' => [
                    'How to use AI agents to automatically create & post content across YouTube, TikTok, Facebook, X, and more',
                    'The simple “keyword-to-content” system that turns one idea into 100s of unique posts and viral videos instantly',
                    'How to train AI agents to promote any affiliate offer or business in a unique brand voice that actually converts',
                    'How beginners are using AI automation to grow social accounts and drive traffic without posting manually every day',
                    'The viral content strategy that finds trending videos in any niche and repurposes them for maximum reach',
                    'How to leverage done-for-you affiliate products and content to start promoting profitable offers immediately',
                    'The “self-scheduling” AI technology that posts at the best times automatically — no manual scheduling needed',
                    'How to turn this into a profitable SMMA or AI automation service and charge clients monthly recurring fees',

                ],
                'webinar_title' => 'WATCH THIS TRAINING COMPLETELY TO BE OUR NEXT SUCCESS STORY',
                'video_url' => 'https://youtu.be/srdOzkwWTnY',
                'affiliate_request_link' => 'https://www.jvzoo.com/affiliate/affiliateinfonew/index/419471',
                'jv_page' => 'https://buzzagents.io/jv/',
            ],
        ];
    }

    /* ─────────────────────────────────────────────────────────
     | 7 opt-in page designs (cycle by index % 7)
     | Each design uses real offer copy: optin_heading,
     | optin_intro, and bullet_points (shown as 2-column cards).
     ───────────────────────────────────────────────────────── */
    private function buildOptinDesign(int $variant, ?array $offer, string $name, string $category, string $style): array
    {
        return match ($variant) {
            0 => $this->designDarkTurquoise($offer, $name, $category),
            1 => $this->designAmberDark($offer, $name, $category),
            2 => $this->designLightTeal($offer, $name, $category),
            3 => $this->designDeepPurple($offer, $name, $category),
            4 => $this->designMinimalWhite($offer, $name, $category),
            5 => $this->designEmeraldDark($offer, $name, $category),
            default => $this->designCrimsonBold($offer, $name, $category),
        };
    }

    private function bulletsHtml(array $bullets, string $checkClass = 'dfy-check'): string
    {
        $out = '';
        foreach ($bullets as $b) {
            $out .= '<li><span class="' . $checkClass . '">✓</span>' . htmlspecialchars($b, ENT_QUOTES) . '</li>' . "\n";
        }
        return $out;
    }

    /* ── Design 0: Dark Navy + Turquoise ── */
    private function designDarkTurquoise(?array $offer, string $name, string $category): array
    {
        $heading = '🔥100% FREE TRAINING REVEALS:';
        $intro    = htmlspecialchars($offer['optin_intro']   ?? "Join this free webinar and discover how to grow your {$category} business fast.", ENT_QUOTES);
        $bullets  = $offer['bullet_points'] ?? [];
        $bulletsHtml = $this->bulletsHtml($bullets);

        $html = <<<HTML
<div class="dfy-page">
  <section class="dfy-hero">
    <div class="dfy-inner">
      <span class="dfy-badge">{$heading}</span>
      <h1 class="dfy-headline">{$intro}</h1>
      <a class="dfy-scroll-btn" href="#dfy-optin-form">Click Here To Register For This FREE Training</a>
      <div class="dfy-divider-line"></div>
      <div class="dfy-bullets-wrap">
        <p class="dfy-bullets-title">What You'll Discover On This FREE Training:</p>
        <ul class="dfy-bullets">
{$bulletsHtml}        </ul>
      </div>
      <div class="dfy-trust">
        <span>✓ 100% Free</span><span>✓ Instant Access</span><span>✓ Limited Spots</span>
      </div>
      <form id="dfy-optin-form" class="dfy-form" data-locked-form="true">
        <p class="dfy-form-heading">Enter Your Details To Get Instant Access</p>
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
.dfy-page{min-height:100vh;background:linear-gradient(140deg,#060d1a 0%,#0d2039 60%,#081a30 100%);display:flex;align-items:center;justify-content:center;padding:48px 16px}
.dfy-inner{max-width:680px;width:100%;text-align:center}
.dfy-badge{display:inline-block;background:rgba(255,80,80,.15);border:1px solid rgba(255,80,80,.4);color:#ff6b6b;padding:8px 22px;border-radius:6px;font-size:12px;font-weight:800;letter-spacing:.06em;margin-bottom:28px;text-transform:uppercase}
.dfy-headline{font-size:clamp(1.5rem,4vw,2.15rem);font-weight:800;color:#fff;line-height:1.35;margin-bottom:28px;max-width:640px;margin-left:auto;margin-right:auto}
.dfy-divider-line{width:60px;height:3px;background:linear-gradient(90deg,#40E0D0,#2dc4b5);border-radius:2px;margin:0 auto 28px}
.dfy-bullets-wrap{background:rgba(255,255,255,.04);border:1px solid rgba(64,224,208,.15);border-radius:16px;padding:28px 32px;margin-bottom:28px;text-align:left}
.dfy-bullets-title{font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#40E0D0;margin-bottom:16px}
.dfy-bullets{list-style:none;display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media(max-width:620px){.dfy-bullets{grid-template-columns:1fr}}
.dfy-bullets li{background:rgba(64,224,208,.06);border:1px solid rgba(64,224,208,.18);border-radius:10px;padding:12px 14px;font-size:.82rem;color:rgba(255,255,255,.82);line-height:1.5;display:flex;gap:8px;align-items:flex-start}
.dfy-check{color:#40E0D0;font-weight:900;flex-shrink:0;margin-top:1px}
.dfy-trust{display:flex;gap:20px;justify-content:center;flex-wrap:wrap;margin-bottom:28px}
.dfy-trust span{font-size:12px;color:#40E0D0;font-weight:700}
.dfy-form{background:rgba(255,255,255,.05);border:1px solid rgba(64,224,208,.25);border-radius:20px;padding:32px;backdrop-filter:blur(12px)}
.dfy-form-heading{font-size:13px;font-weight:700;color:rgba(255,255,255,.6);letter-spacing:.04em;margin-bottom:18px;text-transform:uppercase}
.dfy-input{display:block;width:100%;padding:14px 18px;border:1px solid rgba(255,255,255,.15);border-radius:10px;background:rgba(255,255,255,.07);color:#fff;font-size:15px;outline:none;margin-bottom:12px;transition:border-color .2s}
.dfy-input::placeholder{color:rgba(255,255,255,.3)}
.dfy-input:focus{border-color:#40E0D0;background:rgba(64,224,208,.06)}
.dfy-btn{width:100%;padding:16px;background:linear-gradient(135deg,#40E0D0,#2dc4b5);color:#060d1a;font-size:16px;font-weight:900;border:none;border-radius:10px;cursor:pointer;margin-top:4px;transition:transform .15s,opacity .15s;letter-spacing:.02em}
.dfy-btn:hover{transform:translateY(-2px);opacity:.9}
.dfy-privacy{margin-top:14px;font-size:11.5px;color:rgba(255,255,255,.3)}
.dfy-scroll-btn{display:inline-block;margin:0 auto 22px;padding:12px 18px;border-radius:10px;background:linear-gradient(135deg,#f59e0b,#ea580c);color:#fff;font-size:13px;font-weight:800;text-decoration:none;letter-spacing:.02em;box-shadow:0 8px 20px rgba(0,0,0,.18);transition:transform .15s,opacity .15s}
.dfy-scroll-btn:hover{transform:translateY(-1px);opacity:.92}
html{scroll-behavior:smooth}
CSS;

        return compact('html', 'css');
    }

    /* ── Design 1: Dark Amber / Warm Gold ── */
    private function designAmberDark(?array $offer, string $name, string $category): array
    {
        $heading = '🔥100% FREE TRAINING REVEALS:';
        $intro       = htmlspecialchars($offer['optin_intro']   ?? "Join this free webinar and discover how to grow your {$category} business fast.", ENT_QUOTES);
        $bullets     = $offer['bullet_points'] ?? [];
        $bulletsHtml = $this->bulletsHtml($bullets);

        $html = <<<HTML
<div class="dfy-page">
  <section class="dfy-hero">
    <div class="dfy-inner">
      <span class="dfy-badge">{$heading}</span>
      <h1 class="dfy-headline">{$intro}</h1>
      <a class="dfy-scroll-btn" href="#dfy-optin-form">Click Here To Register For This FREE Training</a>
      <div class="dfy-sep">◆ ◆ ◆</div>
      <div class="dfy-bullets-wrap">
        <p class="dfy-bullets-title">Here's What You'll Learn On This FREE Training:</p>
        <ul class="dfy-bullets">
{$bulletsHtml}        </ul>
      </div>
      <form id="dfy-optin-form" class="dfy-form" data-locked-form="true">
        <p class="dfy-form-heading">🎟 Secure Your Free Spot Below</p>
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
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
.dfy-page{min-height:100vh;background:linear-gradient(160deg,#1a0800 0%,#2d1000 50%,#1a0800 100%);display:flex;align-items:center;justify-content:center;padding:48px 16px}
.dfy-inner{max-width:700px;width:100%;text-align:center}
.dfy-badge{display:inline-block;background:rgba(255,173,0,.15);border:1px solid rgba(255,173,0,.5);color:#FFAD00;padding:8px 22px;border-radius:4px;font-size:12px;font-weight:800;letter-spacing:.1em;margin-bottom:24px;text-transform:uppercase}
.dfy-headline{font-size:clamp(1.45rem,3.8vw,2.1rem);font-weight:800;color:#fff;line-height:1.38;margin-bottom:20px;max-width:660px;margin-left:auto;margin-right:auto}
.dfy-sep{color:rgba(255,173,0,.5);letter-spacing:.3em;margin-bottom:28px;font-size:10px}
.dfy-bullets-wrap{background:rgba(255,173,0,.05);border:1px solid rgba(255,173,0,.18);border-radius:14px;padding:28px 32px;margin-bottom:32px;text-align:left}
.dfy-bullets-title{font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#FFAD00;margin-bottom:16px}
.dfy-bullets{list-style:none;display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media(max-width:620px){.dfy-bullets{grid-template-columns:1fr}}
.dfy-bullets li{background:rgba(255,173,0,.07);border:1px solid rgba(255,173,0,.2);border-radius:10px;padding:12px 14px;font-size:.82rem;color:rgba(255,255,255,.82);line-height:1.5;display:flex;gap:8px;align-items:flex-start}
.dfy-check{color:#FFAD00;font-weight:900;flex-shrink:0;margin-top:1px}
.dfy-form{background:rgba(255,173,0,.06);border:1px solid rgba(255,173,0,.25);border-radius:14px;padding:28px;text-align:left}
.dfy-form-heading{font-size:14px;font-weight:700;color:#FFAD00;margin-bottom:20px;text-align:center}
.dfy-label{display:block;font-size:11px;font-weight:700;letter-spacing:.08em;color:rgba(255,255,255,.45);margin-bottom:6px;text-transform:uppercase}
.dfy-input{display:block;width:100%;padding:13px 16px;border:1px solid rgba(255,173,0,.25);border-radius:8px;background:rgba(255,255,255,.06);color:#fff;font-size:15px;outline:none;margin-bottom:16px;transition:border-color .2s}
.dfy-input::placeholder{color:rgba(255,255,255,.3)}
.dfy-input:focus{border-color:#FFAD00;background:rgba(255,173,0,.05)}
.dfy-btn{width:100%;padding:15px;background:linear-gradient(135deg,#FFAD00,#e69500);color:#1a0800;font-size:15px;font-weight:900;border:none;border-radius:8px;cursor:pointer;transition:opacity .2s,transform .15s;letter-spacing:.02em}
.dfy-btn:hover{opacity:.88;transform:translateY(-1px)}
.dfy-footer-trust{display:flex;gap:24px;justify-content:center;flex-wrap:wrap;margin-top:20px}
.dfy-footer-trust span{font-size:12px;color:rgba(255,255,255,.3)}
.dfy-scroll-btn{display:inline-block;margin:0 auto 22px;padding:12px 18px;border-radius:10px;background:linear-gradient(135deg,#f59e0b,#ea580c);color:#fff;font-size:13px;font-weight:800;text-decoration:none;letter-spacing:.02em;box-shadow:0 8px 20px rgba(0,0,0,.18);transition:transform .15s,opacity .15s}
.dfy-scroll-btn:hover{transform:translateY(-1px);opacity:.92}
html{scroll-behavior:smooth}
CSS;

        return compact('html', 'css');
    }

    /* ── Design 2: Light / Clean Teal ── */
    private function designLightTeal(?array $offer, string $name, string $category): array
    {
        $heading = '🔥100% FREE TRAINING REVEALS:';
        $intro       = htmlspecialchars($offer['optin_intro']   ?? "Join this free webinar and discover how to grow your {$category} business fast.", ENT_QUOTES);
        $bullets     = $offer['bullet_points'] ?? [];
        $bulletsHtml = $this->bulletsHtml($bullets, 'dfy-check');

        $html = <<<HTML
<div class="dfy-page">
  <div class="dfy-card">
    <div class="dfy-card-left">
      <span class="dfy-badge">{$heading}</span>
      <h1 class="dfy-headline">{$intro}</h1>
      <a class="dfy-scroll-btn" href="#dfy-optin-form">Click Here To Register For This FREE Training</a>
      <div class="dfy-divider"></div>
      <p class="dfy-disc-title">What You'll Discover:</p>
      <ul class="dfy-bullets">
{$bulletsHtml}      </ul>
    </div>
    <div class="dfy-card-right">
      <div class="dfy-lock-icon">🔐</div>
      <h2 class="dfy-form-title">Register for Free Access</h2>
      <p class="dfy-form-sub">Enter your details below to claim your spot</p>
      <form id="dfy-optin-form" class="dfy-form" data-locked-form="true">
        <input class="dfy-input" name="name" type="text" placeholder="Your full name" required />
        <input class="dfy-input" name="email" type="email" placeholder="Your best email address" required />
        <button class="dfy-btn" type="submit">Yes! Give Me Instant Access →</button>
        <p class="dfy-privacy">🔒 No spam. Unsubscribe any time.</p>
      </form>
      <div class="dfy-trust-row">
        <span>✓ 100% Free</span><span>✓ No Credit Card</span><span>✓ Instant Access</span>
      </div>
    </div>
  </div>
</div>
HTML;

        $css = <<<CSS
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
.dfy-page{min-height:100vh;background:linear-gradient(135deg,#f0fdf9 0%,#e0f7f3 100%);display:flex;align-items:center;justify-content:center;padding:40px 16px}
.dfy-card{max-width:960px;width:100%;background:#fff;border-radius:24px;box-shadow:0 24px 80px rgba(0,0,0,.1);overflow:hidden;display:grid;grid-template-columns:55% 45%}
@media(max-width:700px){.dfy-card{grid-template-columns:1fr}}
.dfy-card-left{background:linear-gradient(155deg,#0d9488 0%,#065f56 100%);padding:52px 40px;color:#fff;overflow-y:auto}
.dfy-badge{display:inline-block;background:rgba(255,255,255,.18);color:#fff;padding:7px 16px;border-radius:6px;font-size:11px;font-weight:800;letter-spacing:.08em;margin-bottom:20px;text-transform:uppercase}
.dfy-headline{font-size:clamp(1.2rem,2.6vw,1.65rem);font-weight:800;line-height:1.45;margin-bottom:20px}
.dfy-divider{width:48px;height:3px;background:rgba(255,255,255,.4);border-radius:2px;margin-bottom:18px}
.dfy-disc-title{font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.7);margin-bottom:14px}
.dfy-bullets{list-style:none;display:grid;grid-template-columns:1fr 1fr;gap:9px}
@media(max-width:500px){.dfy-bullets{grid-template-columns:1fr}}
.dfy-bullets li{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);border-radius:9px;padding:11px 13px;font-size:.8rem;color:rgba(255,255,255,.88);line-height:1.5;display:flex;gap:8px;align-items:flex-start}
.dfy-check{color:#5eead4;font-weight:900;flex-shrink:0;margin-top:1px}
.dfy-card-right{padding:52px 40px;display:flex;flex-direction:column;justify-content:center;background:#fff}
.dfy-lock-icon{font-size:2rem;margin-bottom:12px;text-align:center}
.dfy-form-title{font-size:1.45rem;font-weight:800;color:#0f172a;margin-bottom:6px;text-align:center}
.dfy-form-sub{font-size:.85rem;color:#64748b;margin-bottom:24px;text-align:center}
.dfy-input{display:block;width:100%;padding:13px 16px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:15px;outline:none;margin-bottom:12px;color:#0f172a;transition:border-color .2s}
.dfy-input:focus{border-color:#0d9488;background:#f0fdf9}
.dfy-btn{width:100%;padding:15px;background:linear-gradient(135deg,#0d9488,#065f56);color:#fff;font-size:15px;font-weight:800;border:none;border-radius:10px;cursor:pointer;margin-top:4px;transition:opacity .2s,transform .15s;letter-spacing:.02em}
.dfy-btn:hover{opacity:.9;transform:translateY(-1px)}
.dfy-privacy{margin-top:10px;font-size:11.5px;color:#94a3b8;text-align:center}
.dfy-trust-row{display:flex;gap:16px;justify-content:center;flex-wrap:wrap;margin-top:18px}
.dfy-trust-row span{font-size:11px;color:#64748b;font-weight:600}
.dfy-scroll-btn{display:inline-block;margin:0 auto 22px;padding:12px 18px;border-radius:10px;background:linear-gradient(135deg,#f59e0b,#ea580c);color:#fff;font-size:13px;font-weight:800;text-decoration:none;letter-spacing:.02em;box-shadow:0 8px 20px rgba(0,0,0,.18);transition:transform .15s,opacity .15s}
.dfy-scroll-btn:hover{transform:translateY(-1px);opacity:.92}
html{scroll-behavior:smooth}
CSS;

        return compact('html', 'css');
    }

    /* ── Design 3: Deep Purple ── */
    private function designDeepPurple(?array $offer, string $name, string $category): array
    {
        $heading = '🔥100% FREE TRAINING REVEALS:';
        $intro       = htmlspecialchars($offer['optin_intro']   ?? "Join this free webinar and discover how to grow your {$category} business fast.", ENT_QUOTES);
        $bullets     = $offer['bullet_points'] ?? [];
        $bulletsHtml = $this->bulletsHtml($bullets);

        $html = <<<HTML
<div class="dfy-page">
  <section class="dfy-hero">
    <div class="dfy-glow"></div>
    <div class="dfy-inner">
      <div class="dfy-eyebrow">{$heading}</div>
      <h1 class="dfy-headline">{$intro}</h1>
      <a class="dfy-scroll-btn" href="#dfy-optin-form">Click Here To Register For This FREE Training</a>
      <div class="dfy-line"></div>
      <div class="dfy-bullets-wrap">
        <p class="dfy-bullets-label">▸ On This FREE Training You Will Discover:</p>
        <ul class="dfy-bullets">
{$bulletsHtml}        </ul>
      </div>
      <div class="dfy-stats">
        <div class="dfy-stat"><strong>10,000+</strong><span>Trained</span></div>
        <div class="dfy-stat-div"></div>
        <div class="dfy-stat"><strong>4.9★</strong><span>Rated</span></div>
        <div class="dfy-stat-div"></div>
        <div class="dfy-stat"><strong>100%</strong><span>Free</span></div>
      </div>
      <form id="dfy-optin-form" class="dfy-form" data-locked-form="true">
        <p class="dfy-form-label">Enter Your Details To Reserve Your Seat</p>
        <div class="dfy-row">
          <input class="dfy-input" name="name" type="text" placeholder="Full name" required />
          <input class="dfy-input" name="email" type="email" placeholder="Email address" required />
        </div>
        <button class="dfy-btn" type="submit">🎟 Claim My Free Seat Now</button>
        <p class="dfy-privacy">Your data is safe. We never share or sell it.</p>
      </form>
    </div>
  </section>
</div>
HTML;

        $css = <<<CSS
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
.dfy-page{min-height:100vh;background:linear-gradient(140deg,#0f0720 0%,#1e0a4e 50%,#0f0720 100%);display:flex;align-items:center;justify-content:center;padding:48px 16px;position:relative;overflow:hidden}
.dfy-glow{position:absolute;top:-15%;left:50%;transform:translateX(-50%);width:900px;height:900px;background:radial-gradient(circle,rgba(139,92,246,.22) 0%,transparent 68%);pointer-events:none}
.dfy-inner{max-width:720px;width:100%;text-align:center;position:relative;z-index:1}
.dfy-eyebrow{display:inline-block;background:rgba(167,139,250,.15);border:1px solid rgba(167,139,250,.4);color:#c4b5fd;padding:8px 22px;border-radius:6px;font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;margin-bottom:24px}
.dfy-headline{font-size:clamp(1.4rem,3.8vw,2.05rem);font-weight:800;color:#fff;line-height:1.4;margin-bottom:24px;max-width:680px;margin-left:auto;margin-right:auto}
.dfy-line{width:56px;height:3px;background:linear-gradient(90deg,#8b5cf6,#6d28d9);border-radius:2px;margin:0 auto 24px}
.dfy-bullets-wrap{background:rgba(139,92,246,.08);border:1px solid rgba(139,92,246,.25);border-radius:16px;padding:26px 30px;margin-bottom:24px;text-align:left}
.dfy-bullets-label{font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#a78bfa;margin-bottom:14px}
.dfy-bullets{list-style:none;display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media(max-width:620px){.dfy-bullets{grid-template-columns:1fr}}
.dfy-bullets li{background:rgba(139,92,246,.1);border:1px solid rgba(139,92,246,.28);border-radius:10px;padding:12px 14px;font-size:.82rem;color:rgba(255,255,255,.82);line-height:1.5;display:flex;gap:8px;align-items:flex-start}
.dfy-check{color:#a78bfa;font-weight:900;flex-shrink:0;margin-top:1px}
.dfy-stats{display:flex;justify-content:center;align-items:center;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:14px 24px;margin-bottom:24px;gap:0;flex-wrap:wrap}
.dfy-stat{text-align:center;padding:0 24px}
.dfy-stat strong{display:block;font-size:1.25rem;font-weight:800;color:#a78bfa}
.dfy-stat span{font-size:11px;color:rgba(255,255,255,.4);display:block;margin-top:2px}
.dfy-stat-div{width:1px;height:32px;background:rgba(255,255,255,.1)}
.dfy-form{background:rgba(255,255,255,.05);border:1px solid rgba(139,92,246,.3);border-radius:16px;padding:28px}
.dfy-form-label{font-size:12px;font-weight:700;color:rgba(255,255,255,.5);letter-spacing:.06em;text-transform:uppercase;margin-bottom:16px}
.dfy-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
@media(max-width:520px){.dfy-row{grid-template-columns:1fr}}
.dfy-input{width:100%;padding:13px 16px;border:1px solid rgba(255,255,255,.12);border-radius:8px;background:rgba(255,255,255,.07);color:#fff;font-size:14px;outline:none;transition:border-color .2s}
.dfy-input::placeholder{color:rgba(255,255,255,.3)}
.dfy-input:focus{border-color:#a78bfa;background:rgba(139,92,246,.08)}
.dfy-btn{width:100%;padding:15px;background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:#fff;font-size:15px;font-weight:800;border:none;border-radius:8px;cursor:pointer;transition:opacity .2s,transform .15s;letter-spacing:.02em}
.dfy-btn:hover{opacity:.9;transform:translateY(-1px)}
.dfy-privacy{margin-top:12px;font-size:11.5px;color:rgba(255,255,255,.25)}
.dfy-scroll-btn{display:inline-block;margin:0 auto 22px;padding:12px 18px;border-radius:10px;background:linear-gradient(135deg,#f59e0b,#ea580c);color:#fff;font-size:13px;font-weight:800;text-decoration:none;letter-spacing:.02em;box-shadow:0 8px 20px rgba(0,0,0,.18);transition:transform .15s,opacity .15s}
.dfy-scroll-btn:hover{transform:translateY(-1px);opacity:.92}
html{scroll-behavior:smooth}
CSS;

        return compact('html', 'css');
    }

    /* ── Design 4: Minimal White ── */
    private function designMinimalWhite(?array $offer, string $name, string $category): array
    {
        $heading = '🔥100% FREE TRAINING REVEALS:';
        $intro       = htmlspecialchars($offer['optin_intro']   ?? "Join this free webinar and discover how to grow your {$category} business fast.", ENT_QUOTES);
        $bullets     = $offer['bullet_points'] ?? [];
        $bulletsHtml = $this->bulletsHtml($bullets, 'dfy-check');

        $html = <<<HTML
<div class="dfy-page">
  <div class="dfy-logo">DFY Webinar Funnels</div>
  <section class="dfy-hero">
    <span class="dfy-badge">{$heading}</span>
    <h1 class="dfy-headline">{$intro}</h1>
      <a class="dfy-scroll-btn" href="#dfy-optin-form">Click Here To Register For This FREE Training</a>
    <div class="dfy-content-grid">
      <div class="dfy-bullets-col">
        <p class="dfy-disc-title">📋 What You'll Discover:</p>
        <ul class="dfy-bullets">
{$bulletsHtml}        </ul>
      </div>
      <div class="dfy-form-col">
        <form id="dfy-optin-form" class="dfy-form" data-locked-form="true">
          <h2 class="dfy-form-title">🔐 Secure Your Free Spot</h2>
          <p class="dfy-form-sub">100% free — no credit card needed</p>
          <input class="dfy-input" name="name" type="text" placeholder="Your first name" required />
          <input class="dfy-input" name="email" type="email" placeholder="Your best email" required />
          <button class="dfy-btn" type="submit">Yes, Give Me Access! →</button>
          <p class="dfy-privacy">🔒 No spam. Unsubscribe any time.</p>
        </form>
        <div class="dfy-trust-row">
          <span>✓ 100% Free</span><span>✓ No Card</span><span>✓ Instant</span>
        </div>
      </div>
    </div>
  </section>
</div>
HTML;

        $css = <<<CSS
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f8fafc}
.dfy-page{min-height:100vh;background:#f8fafc;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:40px 16px}
.dfy-logo{font-size:13px;font-weight:700;color:#0d9488;letter-spacing:.04em;margin-bottom:28px;text-transform:uppercase}
.dfy-hero{max-width:1000px;width:100%;text-align:center}
.dfy-badge{display:inline-block;background:#f0fdf4;color:#0d9488;border:1px solid rgba(13,148,136,.35);padding:7px 18px;border-radius:6px;font-size:12px;font-weight:800;letter-spacing:.07em;margin-bottom:20px;text-transform:uppercase}
.dfy-headline{font-size:clamp(1.35rem,3vw,1.85rem);font-weight:800;color:#0f172a;line-height:1.42;margin-bottom:32px;max-width:820px;margin-left:auto;margin-right:auto}
.dfy-content-grid{display:grid;grid-template-columns:55% 45%;gap:32px;text-align:left}
@media(max-width:700px){.dfy-content-grid{grid-template-columns:1fr}}
.dfy-bullets-col{background:#fff;border:1.5px solid #e2e8f0;border-radius:20px;padding:32px}
.dfy-disc-title{font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#0d9488;margin-bottom:18px}
.dfy-bullets{list-style:none;display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media(max-width:580px){.dfy-bullets{grid-template-columns:1fr}}
.dfy-bullets li{background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:12px 14px;font-size:.8rem;color:#334155;line-height:1.5;display:flex;gap:8px;align-items:flex-start}
.dfy-check{color:#0d9488;font-weight:900;flex-shrink:0;margin-top:2px}
.dfy-form-col{display:flex;flex-direction:column;gap:16px}
.dfy-form{background:#fff;border:1.5px solid #e2e8f0;border-radius:20px;padding:32px;box-shadow:0 8px 30px rgba(0,0,0,.06)}
.dfy-form-title{font-size:1.1rem;font-weight:800;color:#0f172a;margin-bottom:4px}
.dfy-form-sub{font-size:.8rem;color:#94a3b8;margin-bottom:20px}
.dfy-input{display:block;width:100%;padding:13px 16px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:15px;outline:none;margin-bottom:12px;color:#0f172a;transition:border-color .2s}
.dfy-input:focus{border-color:#0d9488;background:#f0fdf9}
.dfy-btn{width:100%;padding:15px;background:linear-gradient(135deg,#0d9488,#065f56);color:#fff;font-size:15px;font-weight:800;border:none;border-radius:10px;cursor:pointer;transition:opacity .15s,transform .15s;margin-top:4px;letter-spacing:.02em}
.dfy-btn:hover{opacity:.9;transform:translateY(-1px)}
.dfy-privacy{margin-top:12px;font-size:11.5px;color:#cbd5e1;text-align:center}
.dfy-trust-row{display:flex;gap:16px;justify-content:center;flex-wrap:wrap}
.dfy-trust-row span{font-size:11px;color:#94a3b8;font-weight:600}
.dfy-scroll-btn{display:inline-block;margin:0 auto 22px;padding:12px 18px;border-radius:10px;background:linear-gradient(135deg,#f59e0b,#ea580c);color:#fff;font-size:13px;font-weight:800;text-decoration:none;letter-spacing:.02em;box-shadow:0 8px 20px rgba(0,0,0,.18);transition:transform .15s,opacity .15s}
.dfy-scroll-btn:hover{transform:translateY(-1px);opacity:.92}
html{scroll-behavior:smooth}
CSS;

        return compact('html', 'css');
    }

    /* ── Design 5: Emerald Dark ── */
    private function designEmeraldDark(?array $offer, string $name, string $category): array
    {
        $heading = '🔥100% FREE TRAINING REVEALS:';
        $intro       = htmlspecialchars($offer['optin_intro']   ?? "Join this free webinar and discover how to grow your {$category} business fast.", ENT_QUOTES);
        $bullets     = $offer['bullet_points'] ?? [];
        $bulletsHtml = $this->bulletsHtml($bullets);

        $html = <<<HTML
<div class="dfy-page">
  <div class="dfy-noise"></div>
  <section class="dfy-hero">
    <div class="dfy-inner">
      <div class="dfy-tag">{$heading}</div>
      <h1 class="dfy-headline">{$intro}</h1>
      <a class="dfy-scroll-btn" href="#dfy-optin-form">Click Here To Register For This FREE Training</a>
      <div class="dfy-accent-bar"></div>
      <div class="dfy-bullets-wrap">
        <div class="dfy-bullets-header">
          <span class="dfy-bullets-icon">🎯</span>
          <span class="dfy-bullets-title">What You'll Discover On This FREE Training:</span>
        </div>
        <ul class="dfy-bullets">
{$bulletsHtml}        </ul>
      </div>
      <form id="dfy-optin-form" class="dfy-form" data-locked-form="true">
        <p class="dfy-form-label">⚡ Enter Your Details For Instant Free Access</p>
        <div class="dfy-fields">
          <input class="dfy-input" name="name" type="text" placeholder="Your full name" required />
          <input class="dfy-input" name="email" type="email" placeholder="Your best email" required />
        </div>
        <button class="dfy-btn" type="submit">Yes! Give Me Free Access Now →</button>
        <p class="dfy-privacy">🔒 Secure &amp; Confidential — No spam, ever.</p>
      </form>
    </div>
  </section>
</div>
HTML;

        $css = <<<CSS
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
.dfy-page{min-height:100vh;background:linear-gradient(145deg,#021a0e 0%,#063020 55%,#021a0e 100%);display:flex;align-items:center;justify-content:center;padding:48px 16px;position:relative;overflow:hidden}
.dfy-noise{position:absolute;inset:0;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");opacity:.4;pointer-events:none}
.dfy-inner{max-width:760px;width:100%;position:relative;z-index:1;text-align:center}
.dfy-tag{display:inline-block;background:rgba(52,211,153,.12);border:1px solid rgba(52,211,153,.4);color:#6ee7b7;padding:8px 22px;border-radius:6px;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;margin-bottom:24px}
.dfy-headline{font-size:clamp(1.4rem,3.8vw,2.05rem);font-weight:800;color:#fff;line-height:1.42;margin-bottom:20px;max-width:720px;margin-left:auto;margin-right:auto}
.dfy-accent-bar{width:64px;height:4px;background:linear-gradient(90deg,#10b981,#059669);border-radius:2px;margin:0 auto 28px}
.dfy-bullets-wrap{background:rgba(16,185,129,.07);border:1px solid rgba(16,185,129,.2);border-radius:18px;padding:28px 32px;margin-bottom:28px;text-align:left}
.dfy-bullets-header{display:flex;align-items:center;gap:10px;margin-bottom:18px}
.dfy-bullets-icon{font-size:1.1rem}
.dfy-bullets-title{font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#34d399}
.dfy-bullets{list-style:none;display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media(max-width:620px){.dfy-bullets{grid-template-columns:1fr}}
.dfy-bullets li{background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.22);border-radius:10px;padding:12px 14px;font-size:.82rem;color:rgba(255,255,255,.83);line-height:1.5;display:flex;gap:8px;align-items:flex-start}
.dfy-check{color:#34d399;font-weight:900;flex-shrink:0;margin-top:1px}
.dfy-form{background:rgba(255,255,255,.04);border:1px solid rgba(16,185,129,.25);border-radius:18px;padding:30px}
.dfy-form-label{font-size:12px;font-weight:700;color:#34d399;letter-spacing:.06em;text-transform:uppercase;margin-bottom:18px}
.dfy-fields{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
@media(max-width:520px){.dfy-fields{grid-template-columns:1fr}}
.dfy-input{width:100%;padding:13px 16px;border:1px solid rgba(16,185,129,.25);border-radius:9px;background:rgba(255,255,255,.06);color:#fff;font-size:14px;outline:none;transition:border-color .2s}
.dfy-input::placeholder{color:rgba(255,255,255,.3)}
.dfy-input:focus{border-color:#10b981;background:rgba(16,185,129,.07)}
.dfy-btn{width:100%;padding:16px;background:linear-gradient(135deg,#10b981,#059669);color:#021a0e;font-size:15px;font-weight:900;border:none;border-radius:10px;cursor:pointer;transition:opacity .2s,transform .15s;letter-spacing:.02em}
.dfy-btn:hover{opacity:.9;transform:translateY(-2px)}
.dfy-privacy{margin-top:12px;font-size:11.5px;color:rgba(255,255,255,.25)}
.dfy-scroll-btn{display:inline-block;margin:0 auto 22px;padding:12px 18px;border-radius:10px;background:linear-gradient(135deg,#f59e0b,#ea580c);color:#fff;font-size:13px;font-weight:800;text-decoration:none;letter-spacing:.02em;box-shadow:0 8px 20px rgba(0,0,0,.18);transition:transform .15s,opacity .15s}
.dfy-scroll-btn:hover{transform:translateY(-1px);opacity:.92}
html{scroll-behavior:smooth}
CSS;

        return compact('html', 'css');
    }

    /* ── Design 6: Crimson Bold Red ── */
    private function designCrimsonBold(?array $offer, string $name, string $category): array
    {
        $heading = '🔥100% FREE TRAINING REVEALS:';
        $intro       = htmlspecialchars($offer['optin_intro']   ?? "Join this free webinar and discover how to grow your {$category} business fast.", ENT_QUOTES);
        $bullets     = $offer['bullet_points'] ?? [];
        $bulletsHtml = $this->bulletsHtml($bullets);

        $html = <<<HTML
<div class="dfy-page">
  <div class="dfy-orb dfy-orb-1"></div>
  <div class="dfy-orb dfy-orb-2"></div>
  <section class="dfy-hero">
    <div class="dfy-inner">
      <div class="dfy-pill">{$heading}</div>
      <h1 class="dfy-headline">{$intro}</h1>
      <a class="dfy-scroll-btn" href="#dfy-optin-form">Click Here To Register For This FREE Training</a>
      <div class="dfy-stripe"></div>
      <div class="dfy-grid">
        <div class="dfy-left">
          <p class="dfy-list-title">✦ WHAT YOU'LL DISCOVER:</p>
          <ul class="dfy-bullets">
{$bulletsHtml}          </ul>
        </div>
        <div class="dfy-right">
          <form id="dfy-optin-form" class="dfy-form" data-locked-form="true">
            <div class="dfy-form-top">
              <span class="dfy-form-badge">FREE ACCESS</span>
              <h2 class="dfy-form-title">Reserve Your Spot</h2>
              <p class="dfy-form-sub">Limited seats available — register now</p>
            </div>
            <input class="dfy-input" name="name" type="text" placeholder="Your full name" required />
            <input class="dfy-input" name="email" type="email" placeholder="Your best email address" required />
            <button class="dfy-btn" type="submit">🔥 Claim My Free Seat Now</button>
            <div class="dfy-trust">
              <span>✓ 100% Free</span><span>✓ Instant Access</span><span>✓ No Card</span>
            </div>
            <p class="dfy-privacy">🔒 Your info is safe. No spam, ever.</p>
          </form>
        </div>
      </div>
    </div>
  </section>
</div>
HTML;

        $css = <<<CSS
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
.dfy-page{min-height:100vh;background:linear-gradient(145deg,#130006 0%,#290010 55%,#130006 100%);display:flex;align-items:center;justify-content:center;padding:48px 16px;position:relative;overflow:hidden}
.dfy-orb{position:absolute;border-radius:50%;pointer-events:none}
.dfy-orb-1{width:600px;height:600px;top:-200px;right:-150px;background:radial-gradient(circle,rgba(220,38,38,.18) 0%,transparent 70%)}
.dfy-orb-2{width:500px;height:500px;bottom:-180px;left:-120px;background:radial-gradient(circle,rgba(185,28,28,.15) 0%,transparent 70%)}
.dfy-inner{max-width:1020px;width:100%;position:relative;z-index:1;text-align:center}
.dfy-pill{display:inline-block;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.45);color:#fca5a5;padding:8px 22px;border-radius:100px;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;margin-bottom:20px}
.dfy-headline{font-size:clamp(1.4rem,3.5vw,2rem);font-weight:800;color:#fff;line-height:1.42;margin-bottom:20px;max-width:900px;margin-left:auto;margin-right:auto}
.dfy-stripe{width:70px;height:4px;background:linear-gradient(90deg,#ef4444,#dc2626);border-radius:2px;margin:0 auto 32px}
.dfy-grid{display:grid;grid-template-columns:58% 42%;gap:28px;text-align:left}
@media(max-width:760px){.dfy-grid{grid-template-columns:1fr}}
.dfy-left{}
.dfy-list-title{font-size:11px;font-weight:800;letter-spacing:.1em;color:#fca5a5;margin-bottom:16px}
.dfy-bullets{list-style:none;display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media(max-width:560px){.dfy-bullets{grid-template-columns:1fr}}
.dfy-bullets li{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.22);border-radius:10px;padding:12px 14px;font-size:.81rem;color:rgba(255,255,255,.83);line-height:1.5;display:flex;gap:8px;align-items:flex-start}
.dfy-check{color:#f87171;font-weight:900;flex-shrink:0;margin-top:1px}
.dfy-right{}
.dfy-form{background:rgba(255,255,255,.04);border:1px solid rgba(239,68,68,.25);border-radius:20px;padding:28px;height:100%}
.dfy-form-top{text-align:center;margin-bottom:22px}
.dfy-form-badge{display:inline-block;background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.4);color:#fca5a5;padding:5px 14px;border-radius:100px;font-size:10px;font-weight:800;letter-spacing:.1em;margin-bottom:10px}
.dfy-form-title{font-size:1.25rem;font-weight:800;color:#fff;margin-bottom:4px}
.dfy-form-sub{font-size:.8rem;color:rgba(255,255,255,.4)}
.dfy-input{display:block;width:100%;padding:13px 16px;border:1px solid rgba(255,255,255,.12);border-radius:9px;background:rgba(255,255,255,.06);color:#fff;font-size:14px;outline:none;margin-bottom:12px;transition:border-color .2s}
.dfy-input::placeholder{color:rgba(255,255,255,.3)}
.dfy-input:focus{border-color:#ef4444;background:rgba(239,68,68,.07)}
.dfy-btn{width:100%;padding:15px;background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff;font-size:15px;font-weight:900;border:none;border-radius:10px;cursor:pointer;transition:opacity .2s,transform .15s;letter-spacing:.02em;margin-bottom:14px}
.dfy-btn:hover{opacity:.9;transform:translateY(-2px)}
.dfy-trust{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-bottom:10px}
.dfy-trust span{font-size:11px;color:rgba(255,255,255,.35);font-weight:600}
.dfy-privacy{font-size:11px;color:rgba(255,255,255,.22);text-align:center}
.dfy-scroll-btn{display:inline-block;margin:0 auto 22px;padding:12px 18px;border-radius:10px;background:linear-gradient(135deg,#f59e0b,#ea580c);color:#fff;font-size:13px;font-weight:800;text-decoration:none;letter-spacing:.02em;box-shadow:0 8px 20px rgba(0,0,0,.18);transition:transform .15s,opacity .15s}
.dfy-scroll-btn:hover{transform:translateY(-1px);opacity:.92}
html{scroll-behavior:smooth}
CSS;

        return compact('html', 'css');
    }
}
