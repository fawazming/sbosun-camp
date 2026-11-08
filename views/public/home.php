<?php
$early = phase() === 'EARLY_BIRD';
$fee = current_fee();
$flat = (int) setting('flat_fee', 12000);
$earlyFee = (int) setting('early_bird_fee', 8500);
$deadline = nice_date((string) setting('early_bird_deadline'), 'j F');
$campStart = (string) setting('camp_start');
$campDates = camp_dates();
$venue = (string) setting('venue_name');
$venueAddress = (string) setting('venue_address');
$delegates = (int) ($counts['participants'] ?? 0);
$highlights = [
    ['tahajjud', '🌙', 'Sacred Tahajjud Prayers', 'Daily at 4:00 AM — Standing in Devotion', 'Experience deep spiritual tranquility in the quiet hours of the night. Stand in congregation under the stars, connecting with your Lord.'],
    ['congregation', '🕌', 'Congregational Solah (All Solaat)', 'Unity in Devotion — Five Times Daily', 'Establish standard prayers in perfect alignment. Build deep bonds, punctuality and spiritual strength with hundreds of brothers and sisters.'],
    ['muaayasha', '🔥', "Mu'aayasha & Morning Riyadoh", 'Daily at 6:20 AM — Adhkar & Team Bonding', 'Morning physical fitness, Hisnul Muslim Adhkar memorisation and cosy campfire stories cultivating deep social integration.'],
    ['duha', '☀️', 'Solaatul Duhaa & Tilawah', 'Daily at 10:31 AM — Morning Serenity', 'Dedicated quiet hours for individual Duhaa prayer, personal dua and structured Tilawat-ul-Quran.'],
    ['lecture', '📖', 'Transformative Lectures', 'Led by Renowned National Scholars', 'Exquisite sessions unlocking classical wisdom, Tafseer, everyday worship habits and Quranic perspectives on modern challenges.'],
    ['it_session', '💻', 'IT & Digital Skills Session', 'Digital Safety & Technological Literacy', 'A cutting-edge workshop teaching safe internet habits, cyber ethics, digital safety tools and essential tech skills.'],
    ['sports', '🏆', 'Sports & Games (Football/Scrabble)', 'Teamwork, Fitness & Mental Strategy', 'Lace up for our competitive football cup tournament or test your lexicon in the scrabble league.'],
    ['quranic', '🏅', 'Quranic Musabaqoh & Competitions', 'The Prestigious Quranic Arena', 'An inspiring annual platform celebrating memorisation, beautiful recitation, tajweed accuracy and high-pressure quiz challenges.'],
    ['single', '🎓', 'Single Seminar Panel', 'Morality, Identity & Career Growth', 'Empowering conversations for secondary students and graduates navigating peer pressure, digital ethics and self-discipline.'],
];
$gallery = [
    ['memory_1.jpg', 'Our Grand Camp Arrival', 'Oduduwa College Campus', 'More than 450 delegates standing together under the banner of faith, reflecting unity, purpose and mutual love.', true],
    ['memory_2.jpg', 'Interactive Knowledge Assembly', 'Main Auditorium', 'Attentive delegates listening, taking notes and engaging in deep discussions during the morning academic series.', false],
    ['memory_3.jpg', 'Scholarly Dars Sessions', 'Central Lecture Theatre', 'Acquiring authentic divine knowledge under the tutelage of highly respected scholars and national mentors.', false],
    ['memory_4.jpg', 'Quranic Focus & Deep Tilawah', 'Central Musollah', 'Individual reflection and beautiful recitations during quiet morning and evening Quran memorisation hours.', false],
    ['memory_5.jpg', 'Campus Walk & Shared Reflection', 'Oduduwa Courtyard', 'Forging lifelong bonds of Islamic brotherhood during informal strolls and team-building campfire sessions.', false],
    ['memory_6.jpg', 'Youth Moral Integrity Seminar', 'Seminar Hall A', 'An engaging panel discussion addressing modern societal challenges, moral uprightness and professional excellence.', false],
    ['memory_7.jpg', 'Spiritual Attentiveness', 'Central Musollah', 'Focusing on purification of the soul through individual supplications and silent devotion between congregational solat.', false],
    ['memory_8.jpg', 'Quranic Recitation Finalists', 'Main Auditorium', 'A serene platform celebrating the finalists of our annual high-stakes Tajweed accuracy and memorisation challenge.', false],
    ['memory_9.jpg', 'Smiling Faces of Brotherhood', 'Oduduwa Gardens', 'Delegates radiating genuine joy and spiritual fulfilment, building a support network for academic and personal life.', false],
    ['memory_10.jpg', 'Mentors Guidance Circle', 'Conference Room B', 'Mentors and counsellors leading active leadership forums to prepare delegates as shining standard-bearers in society.', false],
    ['memory_11.jpg', 'Morning Campfire Stories', 'SB Campfire Arena', 'Sharing inspiring experiences, historical narratives and standard-bearer challenges during chilly campfire gatherings.', false],
    ['memory_12.jpg', 'Digital Tech Workshops', 'IT Innovation Lab', 'Hands-on training covering digital ethics, professional technology pathways and cybersecurity literacy.', false],
    ['memory_13.jpg', 'Resilient Scholar Presentation', 'Main Auditorium', 'Scholars unlocking classical Islamic sciences and offering blueprints to build sound, resilient family frameworks.', false],
    ['memory_14.jpg', 'Moments of Sincere Supplication', 'Central Musollah', 'Delegates raising their hands in emotional personal dua, seeking forgiveness and success in this world and the next.', false],
    ['memory_15.jpg', 'Standing in Congregation', 'SAB Arena Field', 'Rows of delegates standing shoulder to shoulder, embodying unity and discipline during congregational prayer.', false],
    ['memory_16.jpg', 'Pre-Dawn Night Prayers', 'Oduduwa Courtyard', 'Standing in Qiyamul Layl under starlit nights, pouring out sincere prostrations during the sacred Tahajjud hours.', false],
    ['memory_17.jpg', 'Camp Brotherhood Meals', 'Dining Hall Complex', 'Sharing warm nutritious meals in humility, embodying the sunnah of eating together and avoiding waste.', false],
    ['memory_18.jpg', 'Fresh Morning Assembly', 'Camp Assembly Field', 'Starting the day with fresh morning Adhkar, brief physical warm-ups and a review of the daily high-impact events.', false],
    ['memory_19.jpg', 'Graduates Leadership Forum', 'Auditorium Quad', 'A special group photo with senior mentors and speakers after a highly empowering moral session.', false],
    ['memory_20.jpg', 'Vibrant Quiz Tournament', 'Activity Center', 'Stimulating board games and rapid-fire quiz tournaments fostering healthy competition and mental strategy.', false],
    ['memory_21.jpg', 'Dedicated Worship Rows', 'Oduduwa College Ground', 'Organised rows reflecting absolute humility, focus and submission before Allah the Almighty.', false],
    ['memory_22.jpg', 'Healthy Physical Fitness', 'Sports Ground', 'Daily morning exercises and energetic sports leagues reinforcing standard physical and cardiovascular fitness.', true],
];
$lecturers = [
    ["Sheikh Dhikrullah Shafi'i", 'Eminent Scholar & Spiritual Director', 'images/lecturers/sheikh-dhikrullah-shafii.jpg'],
    ['Sheikh Abdulkabir Hamzah', 'Tafseer Expert & OTM Chief Imam', null],
    ['Ustadh Rasheed Hashim', 'Digital Safety Counselor', null],
    ['Sheikh Isa Akindede', 'Spiritual Guide & Quran Scholar', null],
];
?>
<main>
  <!-- HERO -->
  <section class="hero">
    <div class="container hero-grid">
      <div>
        <p class="hero-kicker">Holiday Islamic Course</p>
        <h1 class="hero-title">SB HIC<br><span class="gold"><?= e((string) setting('year', '2026')) ?></span></h1>
        <p class="hero-copy">Five days of learning, brotherhood and sisterhood for JSS students through graduates — grouped into a class built for where you are.</p>
        <p class="hero-meta"><?= e($campDates) ?> · <?= e($venue) ?>, <?= e($venueAddress) ?></p>
        <div class="hero-actions">
          <a class="btn btn-gold" href="<?= e(url('register')) ?>">Register for Camp</a>
          <a class="btn btn-outline" href="#classes">Learn More</a>
          <a class="btn btn-outline" href="<?= e(url('event.ics')) ?>" title="Add camp dates to your calendar">＋ Calendar</a>
        </div>
      </div>
      <div class="hero-cards" data-reveal>
        <div class="rate-card">
          <p class="rate-label"><?= $early ? 'Early Bird Rate' : 'Camp Rate' ?></p>
          <p class="rate-value"><?= money($fee) ?> <?php if ($early): ?><span class="rate-was"><?= money($flat) ?></span><?php endif; ?></p>
          <p class="rate-note"><span data-countdown="<?= e($campStart) ?>"></span><?= $early ? ' · Closes ' . e($deadline) : ' · Registration open through camp' ?></p>
        </div>
        <div class="plan-card">
          <p class="plan-label">Pay how it suits you</p>
          <p class="plan-title">Full payment or 10 instalments</p>
          <p class="plan-note"><?= $early ? 'Instalment plan must be completed before ' . e($deadline) : 'Instalments close when early bird ends — pay in full to secure your place.' ?></p>
        </div>
      </div>
    </div>
  </section>

  <!-- CLASSES -->
  <section class="section" id="classes">
    <div class="container">
      <div data-reveal style="max-width:620px;margin-bottom:30px">
        <p class="eyebrow">Your class</p>
        <h2 class="display" style="margin-top:5px">You don’t choose a class — we place you in one.</h2>
        <p class="lede" style="margin-top:9px">Register with your school category and zone, and your class is assigned automatically.</p>
      </div>
      <div class="grid grid-3">
        <div class="card class-card" data-reveal>
          <p class="tag">JSS1 – JSS3</p>
          <h3>Lower Class</h3>
          <p>For junior secondary students beginning their journey with the fundamentals of the standard.</p>
        </div>
        <div class="card class-card" data-reveal>
          <p class="tag">SS1 – SSS3</p>
          <h3>Upper Class</h3>
          <p>For senior secondary students building on that foundation as they prepare for what’s next.</p>
        </div>
        <div class="card class-card" data-reveal>
          <p class="tag">School Leaver · Undergraduate · Graduate</p>
          <h3>Dawah Class</h3>
          <p>For those carrying the standard forward — into higher education, work and the wider community.</p>
        </div>
      </div>
      <div class="grid grid-4" style="margin-top:22px">
        <div class="card" data-reveal><p class="eyebrow">Delegates</p><p style="font-size:28px;font-weight:900;margin-top:3px"><?= $delegates > 0 ? number_format($delegates) . '+' : '450+' ?></p><p class="small muted">Already registered for camp</p></div>
        <div class="card" data-reveal><p class="eyebrow">Zones</p><p style="font-size:28px;font-weight:900;margin-top:3px"><?= max(19, (int) ($counts['zones'] ?? 0)) ?></p><p class="small muted">Across Osun and beyond</p></div>
        <div class="card" data-reveal><p class="eyebrow">Duration</p><p style="font-size:28px;font-weight:900;margin-top:3px">5 days</p><p class="small muted">Learning, worship & brotherhood</p></div>
        <div class="card" data-reveal><p class="eyebrow">Classes</p><p style="font-size:28px;font-weight:900;margin-top:3px">3</p><p class="small muted">Lower · Upper · Dawah</p></div>
      </div>
    </div>
  </section>

  <!-- HIGHLIGHTS -->
  <section class="section section-dark" id="highlights">
    <div class="container">
      <div data-reveal style="max-width:620px">
        <p class="eyebrow">A day at SB HIC</p>
        <h2 class="display serif" style="margin-top:5px;font-style:italic">Every camp leaves a story behind.</h2>
        <p class="lede" style="margin-top:9px">Scroll through what a real day looks like — from pre-dawn Tahajjud to the closing seminar.</p>
      </div>

      <div class="highlight-layout">
        <div class="highlight-list">
          <?php foreach ($highlights as $i => [$id, $icon, $title, $sub, $desc]): ?>
            <article class="highlight-card <?= $i === 0 ? 'active' : '' ?>" data-highlight="<?= e($id) ?>" tabindex="0">
              <div class="hc-top">
                <span class="hc-icon"><?= $icon ?></span>
                <span class="hc-label">Camp feature <?= $i + 1 ?></span>
              </div>
              <h3><?= e($title) ?></h3>
              <p class="hc-sub"><?= e($sub) ?></p>
              <p class="hc-desc"><?= e($desc) ?></p>
            </article>
          <?php endforeach; ?>
        </div>

        <div class="highlight-panel">
          <div class="player-bar">
            <div class="player-dots"><span></span><span></span><span></span></div>
            <div class="live-pill"><span class="live-dot"></span>Live render · SB HIC 4K</div>
          </div>

          <div class="player-stage">
            <div data-panel="tahajjud" data-gender-switch>
              <div class="player-meta">
                <span>ATMOSPHERE: PRE-DAWN DEVOTION</span>
                <div class="gender-toggle">
                  <button type="button" data-gender="female" class="active">Sisters</button>
                  <button type="button" data-gender="male">Brothers</button>
                </div>
              </div>
              <div class="player-media">
                <img data-gender-frame="female" src="<?= e(asset('images/my_tahajjud_female.jpg')) ?>" alt="Sisters at Tahajjud">
                <img data-gender-frame="male" src="<?= e(asset('images/my_tahajjud_male.jpg')) ?>" alt="Brothers at Tahajjud" hidden>
              </div>
              <div class="player-caption">✨ Reviving the heart through tranquil pre-dawn prostrations and sincere congregational worship</div>
            </div>

            <div data-panel="congregation" hidden>
              <div class="player-meta"><span>CONGREGATION: FIVE DAILY PRAYERS</span><span>STATUS: LIVE FEED (MUTED)</span></div>
              <div class="player-media"><video src="<?= e(asset('videos/camp_feature_2.mp4')) ?>" muted autoplay playsinline loop preload="metadata"></video></div>
              <div class="player-caption">🤲 Establishing Solah on time, standing shoulder-to-shoulder in complete submission</div>
            </div>

            <div data-panel="muaayasha" hidden>
              <div class="player-meta"><span>CAMPFIRE & TEAM BONDING</span><span>STATUS: ADHKAR & REFLECTION</span></div>
              <div class="player-media"><img src="<?= e(asset('images/highlights/feature_3_1.jpg')) ?>" alt="Mu'aayasha and morning Riyadoh"></div>
              <div class="player-caption">🔥 Interactive circles cultivating deep bonds, fitness routines and campfire story nights</div>
            </div>

            <div data-panel="duha" hidden>
              <div class="player-meta"><span>FORENOON: SOLAATUL DUHAA</span><span>THEME: TILAWAT-UL-QURAN & DUA</span></div>
              <div class="player-media"><img src="<?= e(asset('images/highlights/feature_4_1.jpg')) ?>" alt="Solaatul Duhaa and Tilawah"></div>
              <div class="player-caption">☀️ Nurturing morning mindfulness and individual worship before daily seminars begin</div>
            </div>

            <div data-panel="lecture" hidden>
              <div class="player-meta"><span>TRANSFORMATIVE LECTURES & SERMONS</span><span id="lectureCounter">PHOTO 1 OF 9</span></div>
              <div class="player-media" data-carousel data-counter="lectureCounter">
                <?php foreach (['feature_6_2', 'feature_5_2', 'feature_5_3', 'feature_5_4', 'feature_5_5', 'feature_5_6', 'feature_5_7', 'feature_5_8', 'feature_5_9'] as $n => $img): ?>
                  <img data-frame src="<?= e(asset('images/highlights/' . $img . '.jpg')) ?>" alt="Lecture highlight <?= $n + 1 ?>" <?= $n > 0 ? 'hidden' : '' ?>>
                <?php endforeach; ?>
                <button type="button" class="media-nav prev" data-prev aria-label="Previous photo">←</button>
                <button type="button" class="media-nav next" data-next aria-label="Next photo">→</button>
              </div>
              <div class="player-caption">📖 Pursuing authentic intellectual growth and purifying daily action with divine knowledge</div>
            </div>

            <div data-panel="it_session" hidden>
              <div class="player-meta"><span>IT & DIGITAL SKILLS WORKSHOPS</span><span id="itCounter">PHOTO 1 OF 3</span></div>
              <div class="player-media" data-carousel data-counter="itCounter">
                <?php foreach (['feature_6_1', 'feature_6_2', 'feature_6_3'] as $n => $img): ?>
                  <img data-frame src="<?= e(asset('images/highlights/' . $img . '.jpg')) ?>" alt="IT workshop <?= $n + 1 ?>" <?= $n > 0 ? 'hidden' : '' ?>>
                <?php endforeach; ?>
                <button type="button" class="media-nav prev" data-prev aria-label="Previous photo">←</button>
                <button type="button" class="media-nav next" data-next aria-label="Next photo">→</button>
              </div>
              <div class="player-caption">💻 Harnessing modern digital tools to add value and build ethical tech careers</div>
            </div>

            <div data-panel="sports" hidden>
              <div class="player-meta"><span>ATHLETICS & INDOOR GAMES</span><span id="sportsCounter">PHOTO 1 OF 5</span></div>
              <div class="player-media" data-carousel data-counter="sportsCounter">
                <?php for ($n = 1; $n <= 5; $n++): ?>
                  <img data-frame src="<?= e(asset('images/sports/sports_' . $n . '.jpg')) ?>" alt="Camp sports <?= $n ?>" <?= $n > 1 ? 'hidden' : '' ?>>
                <?php endfor; ?>
                <button type="button" class="media-nav prev" data-prev aria-label="Previous photo">←</button>
                <button type="button" class="media-nav next" data-next aria-label="Next photo">→</button>
              </div>
              <div class="player-caption">⚽ Sports & athletics: nurturing fitness, friendly sportsmanship and mental vocabulary challenges</div>
            </div>

            <div data-panel="quranic" hidden>
              <div class="player-meta"><span>QURANIC COMPETITIONS (MUSABAQOH)</span><span>STATUS: LIVE STREAM / REPLAY</span></div>
              <div class="player-media"><video src="<?= e(asset('videos/camp_feature_8.mp4')) ?>" controls muted autoplay playsinline loop preload="metadata"></video></div>
              <div class="player-caption">🔊 Press play to hear the soul-stirring recitations of our Quranic contestants</div>
            </div>

            <div data-panel="single" hidden>
              <div class="player-meta"><span>SINGLE SEMINAR PANEL</span><span id="singleCounter">PHOTO 1 OF 3</span></div>
              <div class="player-media" data-carousel data-counter="singleCounter">
                <?php foreach (['feature_9_1', 'feature_9_2', 'feature_9_3'] as $n => $img): ?>
                  <img data-frame src="<?= e(asset('images/highlights/' . $img . '.jpg')) ?>" alt="Seminar highlight <?= $n + 1 ?>" <?= $n > 0 ? 'hidden' : '' ?>>
                <?php endforeach; ?>
                <button type="button" class="media-nav prev" data-prev aria-label="Previous photo">←</button>
                <button type="button" class="media-nav next" data-next aria-label="Next photo">→</button>
              </div>
              <div class="player-caption">🎓 Morality, identity and career growth seminars for secondary students and graduates</div>
            </div>
          </div>

          <div class="player-footer">
            <div style="display:flex;align-items:center;gap:12px"><span>▶</span><span style="display:flex;align-items:center;gap:6px"><i style="width:7px;height:7px;border-radius:50%;background:#dc2626;display:inline-block"></i>00:15 / 00:15 LIVE</span></div>
            <div style="display:flex;align-items:center;gap:12px"><span>🔊</span><div class="timeline"><span></span></div><span>⛶</span></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- GALLERY -->
  <section class="section" id="gallery">
    <div class="container">
      <div data-reveal style="max-width:620px;margin-bottom:26px">
        <p class="eyebrow">Camp Memories</p>
        <h2 class="display" style="margin-top:5px">Moments from HIC 2025.</h2>
        <p class="lede" style="margin-top:9px">A look back at the people, the lectures and the brotherhood from last year’s camp.</p>
      </div>
      <div class="gallery-grid">
        <?php foreach ($gallery as [$img, $gTitle, $loc, $desc, $wide]): ?>
          <div class="gallery-item <?= $wide ? 'wide' : '' ?>" data-gallery data-src="<?= e(asset('images/gallery/' . $img)) ?>" data-title="<?= e($gTitle) ?>" data-desc="<?= e($desc) ?>" data-location="<?= e($loc) ?>" data-year="HIC 2025" data-reveal>
            <img src="<?= e(asset('images/gallery/' . $img)) ?>" alt="<?= e($gTitle) ?>" loading="lazy">
            <span class="g-badge">✦ Camp Happy Moments Scenes</span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- LECTURERS -->
  <section class="section" id="lecturers">
    <div class="container">
      <div data-reveal style="max-width:620px;margin-bottom:22px">
        <p class="eyebrow">Meet the faculty</p>
        <h2 class="display" style="margin-top:5px">Learn from the people leading the sessions.</h2>
        <p class="lede" style="margin-top:9px">Our scholars for SB HIC <?= e((string) setting('year', '2026')) ?>.</p>
      </div>
      <div class="lecturer-bar">
        <div class="lecturer-hint">✦ Swipe or drag to explore our certified scholars</div>
        <div style="display:flex;gap:9px">
          <button class="icon-button" type="button" data-lecturer-left aria-label="Scroll left">←</button>
          <button class="icon-button" type="button" data-lecturer-right aria-label="Scroll right">→</button>
        </div>
      </div>
      <div class="lecturer-scroll" data-lecturer-scroll>
        <?php foreach ($lecturers as [$name, $role, $photo]): ?>
          <article class="lecturer-card">
            <div class="lc-head">
              <h4><?= e($name) ?></h4>
              <p class="lc-role"><?= e($role) ?></p>
            </div>
            <div class="lc-photo">
              <?php if ($photo): ?>
                <img src="<?= e(asset($photo)) ?>" alt="<?= e($name) ?>" loading="lazy">
              <?php else: ?>
                <span class="lc-initials"><?= e(initials($name)) ?></span>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <div class="scroll-progress"><span data-lecturer-progress></span></div>
    </div>
  </section>

  <!-- PRICING -->
  <section class="section" id="register">
    <div class="container">
      <div class="pricing-box" data-reveal>
        <div>
          <h2>Register before <?= e($deadline) ?> to lock in the early-bird rate.</h2>
          <p>After that, registration stays open right up to camp — just at the flat rate. Every fee covers accommodation, feeding and welfare for all five days.</p>
          <a class="btn btn-gold" style="margin-top:16px" href="<?= e(url('register')) ?>">Start Registration</a>
          <a class="btn btn-outline" style="margin-top:16px;margin-left:8px" href="<?= e(url('status')) ?>">Find my registration</a>
        </div>
        <div class="price-pair">
          <div class="price-tile gold">
            <p class="pt-label">Early Bird</p>
            <p class="pt-value"><?= money($earlyFee) ?></p>
            <p class="pt-note">Register &amp; pay before <?= e($deadline) ?></p>
          </div>
          <div class="price-tile ghost">
            <p class="pt-label">Flat Rate</p>
            <p class="pt-value"><?= money($flat) ?></p>
            <p class="pt-note">From <?= e(nice_date((string) setting('early_bird_deadline') . ' +1 day', 'j M')) ?> through camp</p>
          </div>
        </div>
      </div>

      <div class="newsletter-band" id="newsletter" style="margin-top:22px" data-reveal>
        <div>
          <p class="eyebrow">Stay in the loop</p>
          <h3 style="font-size:22px;margin-top:4px">Get camp updates by email.</h3>
          <p class="small muted" style="margin-top:5px">Registration reminders, what to bring, and live camp information. Unsubscribe any time.</p>
        </div>
        <form class="newsletter-form" method="post" action="<?= e(url('newsletter')) ?>" data-newsletter-form>
          <?= csrf_field() ?>
          <input class="input" type="email" name="email" placeholder="name@example.com" aria-label="Email address" required>
          <button class="btn btn-green" type="submit">Subscribe</button>
        </form>
        <p class="small" data-newsletter-message style="grid-column:1/-1;margin:0"></p>
      </div>
    </div>
  </section>
</main>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" aria-hidden="true">
  <div class="lightbox-inner" role="dialog" aria-modal="true" aria-label="Camp memory">
    <div class="lightbox-media" id="lightboxMedia"></div>
    <button class="lightbox-close" id="lightboxClose" type="button" aria-label="Close">✕</button>
    <div class="lightbox-body">
      <div class="lb-meta" id="lightboxMeta"></div>
      <h3 id="lightboxTitle"></h3>
      <p id="lightboxDesc"></p>
      <p style="margin-top:14px;font-size:11px;color:#94a3b8">Standard Bearers Islamic Organization, Osun State · HIC 2025 memories</p>
    </div>
  </div>
</div>
