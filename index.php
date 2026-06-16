<?php
// index.php — VisitCI
// Aucune config BDD ici, tout est dans api/chat.php
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VisitCI — Votre guide touristique IA</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0A0F1C;
  --bg2:#111827;
  --bg3:#1a2235;
  --gold:#F4A426;
  --gold2:#ffd07a;
  --teal:#00C896;
  --teal2:#00ffbe;
  --white:#F0EDE6;
  --muted:#8892a4;
  --card:#151d2e;
  --border:rgba(244,164,38,0.15);
  --r:16px;
}
html{scroll-behavior:smooth}
body{
  background:var(--bg);
  color:var(--white);
  font-family:'Inter',sans-serif;
  overflow-x:hidden;
  min-height:100vh;
}

/* ── CANVAS PARTICULES ── */
#particles-canvas{
  position:fixed;top:0;left:0;width:100%;height:100%;
  pointer-events:none;z-index:0;opacity:0.6;
}

/* ── NAV ── */
nav{
  position:fixed;top:0;left:0;right:0;z-index:100;
  padding:1.2rem 2rem;
  display:flex;align-items:center;justify-content:space-between;
  background:rgba(10,15,28,0.7);
  backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border);
  transition:all .3s;
}
.nav-logo{
  font-family:'Playfair Display',serif;
  font-size:1.5rem;font-weight:900;
  background:linear-gradient(135deg,var(--gold),var(--teal));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
  background-clip:text;
  letter-spacing:-0.5px;
}
.nav-links{display:flex;gap:2rem;list-style:none}
.nav-links a{
  color:var(--muted);font-size:.9rem;font-weight:500;
  text-decoration:none;transition:color .2s;
  position:relative;
}
.nav-links a::after{
  content:'';position:absolute;bottom:-4px;left:0;
  width:0;height:2px;background:var(--gold);
  transition:width .3s;
}
.nav-links a:hover{color:var(--white)}
.nav-links a:hover::after{width:100%}
.nav-cta{
  padding:.6rem 1.4rem;border-radius:50px;
  background:linear-gradient(135deg,var(--gold),#e8920a);
  color:#0A0F1C;font-weight:600;font-size:.85rem;
  text-decoration:none;transition:transform .2s,box-shadow .2s;
  box-shadow:0 4px 20px rgba(244,164,38,0.3);
}
.nav-cta:hover{transform:translateY(-2px);box-shadow:0 8px 30px rgba(244,164,38,0.5)}

/* ── HERO ── */
.hero{
  position:relative;z-index:1;
  min-height:100vh;
  display:flex;flex-direction:column;
  align-items:center;justify-content:center;
  text-align:center;
  padding:8rem 2rem 4rem;
  overflow:hidden;
}
.hero-eyebrow{
  display:inline-flex;align-items:center;gap:.5rem;
  background:rgba(0,200,150,0.1);
  border:1px solid rgba(0,200,150,0.3);
  color:var(--teal);font-size:.8rem;font-weight:600;
  padding:.4rem 1rem;border-radius:50px;
  margin-bottom:2rem;
  letter-spacing:1px;text-transform:uppercase;
  animation:fadeDown .8s ease both;
}
.hero-eyebrow span{
  width:6px;height:6px;border-radius:50%;
  background:var(--teal);
  animation:pulse 1.5s infinite;
}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}

.hero-title{
  font-family:'Playfair Display',serif;
  font-size:clamp(2.8rem,7vw,6rem);
  font-weight:900;line-height:1.05;
  margin-bottom:1.5rem;
  animation:fadeUp .9s .1s ease both;
}
.hero-title .line1{display:block;color:var(--white)}
.hero-title .line2{
  display:block;
  background:linear-gradient(135deg,var(--gold) 30%,var(--teal) 100%);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
  background-clip:text;
}
.hero-sub{
  max-width:560px;
  color:var(--muted);font-size:1.1rem;line-height:1.7;
  margin-bottom:3rem;
  animation:fadeUp .9s .2s ease both;
}
.hero-actions{
  display:flex;gap:1rem;flex-wrap:wrap;justify-content:center;
  animation:fadeUp .9s .3s ease both;
}
.btn-primary{
  display:inline-flex;align-items:center;gap:.6rem;
  padding:.85rem 2rem;border-radius:50px;
  background:linear-gradient(135deg,var(--gold),#e8920a);
  color:#0A0F1C;font-weight:700;font-size:1rem;
  text-decoration:none;cursor:pointer;border:none;
  transition:transform .2s,box-shadow .2s;
  box-shadow:0 4px 30px rgba(244,164,38,0.4);
}
.btn-primary:hover{transform:translateY(-3px);box-shadow:0 10px 40px rgba(244,164,38,0.6)}
.btn-secondary{
  display:inline-flex;align-items:center;gap:.6rem;
  padding:.85rem 2rem;border-radius:50px;
  border:1px solid var(--border);
  background:rgba(255,255,255,0.03);
  color:var(--white);font-size:1rem;font-weight:500;
  text-decoration:none;transition:all .2s;
}
.btn-secondary:hover{border-color:var(--teal);color:var(--teal);background:rgba(0,200,150,0.05)}

.hero-stats{
  display:flex;gap:3rem;margin-top:4rem;
  animation:fadeUp .9s .5s ease both;
}
.stat{text-align:center}
.stat-num{
  font-family:'Playfair Display',serif;
  font-size:2.2rem;font-weight:900;
  background:linear-gradient(135deg,var(--gold),var(--teal));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
  background-clip:text;
}
.stat-label{font-size:.8rem;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-top:.2rem}

/* ── SCROLL INDICATOR ── */
.scroll-indicator{
  position:absolute;bottom:2rem;left:50%;transform:translateX(-50%);
  display:flex;flex-direction:column;align-items:center;gap:.5rem;
  color:var(--muted);font-size:.75rem;letter-spacing:2px;text-transform:uppercase;
  animation:fadeUp 1s .8s ease both;
}
.scroll-line{
  width:1px;height:40px;background:linear-gradient(to bottom,var(--gold),transparent);
  animation:scrollDown 1.5s ease infinite;
}
@keyframes scrollDown{0%{transform:scaleY(0);transform-origin:top}50%{transform:scaleY(1);transform-origin:top}51%{transform:scaleY(1);transform-origin:bottom}100%{transform:scaleY(0);transform-origin:bottom}}

/* ── SECTION COMMUNE ── */
section{position:relative;z-index:1;padding:6rem 2rem}
.section-eyebrow{
  display:inline-block;
  color:var(--gold);font-size:.75rem;font-weight:600;
  text-transform:uppercase;letter-spacing:2px;
  margin-bottom:1rem;
}
.section-title{
  font-family:'Playfair Display',serif;
  font-size:clamp(2rem,4vw,3rem);
  font-weight:700;line-height:1.15;
  margin-bottom:1rem;
}
.section-title span{
  background:linear-gradient(135deg,var(--gold),var(--teal));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
  background-clip:text;
}
.section-sub{color:var(--muted);max-width:500px;line-height:1.7}
.container{max-width:1200px;margin:0 auto}

/* ── FEATURES GRID ── */
.features-header{text-align:center;margin-bottom:4rem}
.features-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
  gap:1.5rem;
}
.feature-card{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:var(--r);
  padding:2rem;
  transition:transform .3s,border-color .3s,box-shadow .3s;
  position:relative;overflow:hidden;
  opacity:0;transform:translateY(30px);
}
.feature-card.visible{animation:cardIn .6s ease forwards}
@keyframes cardIn{to{opacity:1;transform:translateY(0)}}
.feature-card::before{
  content:'';position:absolute;top:0;left:0;right:0;height:2px;
  background:linear-gradient(90deg,var(--gold),var(--teal));
  transform:scaleX(0);transform-origin:left;
  transition:transform .4s;
}
.feature-card:hover{
  transform:translateY(-6px);
  border-color:rgba(244,164,38,0.4);
  box-shadow:0 20px 60px rgba(0,0,0,0.4);
}
.feature-card:hover::before{transform:scaleX(1)}
.feature-icon{
  width:52px;height:52px;border-radius:12px;
  background:linear-gradient(135deg,rgba(244,164,38,0.15),rgba(0,200,150,0.15));
  display:flex;align-items:center;justify-content:center;
  font-size:1.5rem;margin-bottom:1.2rem;
  border:1px solid rgba(244,164,38,0.2);
}
.feature-title{font-size:1.1rem;font-weight:600;margin-bottom:.6rem}
.feature-desc{color:var(--muted);font-size:.9rem;line-height:1.6}

/* ── CATÉGORIES ── */
#categories{background:var(--bg2)}
.categories-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
  gap:1rem;margin-top:3rem;
}
.cat-card{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:var(--r);
  padding:1.5rem 1rem;
  text-align:center;cursor:pointer;
  transition:all .3s;
  opacity:0;transform:scale(0.9);
}
.cat-card.visible{animation:popIn .5s ease forwards}
@keyframes popIn{to{opacity:1;transform:scale(1)}}
.cat-card:hover{
  border-color:var(--gold);
  background:rgba(244,164,38,0.06);
  transform:translateY(-4px) scale(1.02);
}
.cat-icon{font-size:2rem;margin-bottom:.7rem;display:block}
.cat-name{font-size:.85rem;font-weight:600;color:var(--white)}
.cat-count{font-size:.75rem;color:var(--muted);margin-top:.3rem}

/* ── CHAT SECTION ── */
#chat-section{
  background:linear-gradient(135deg,var(--bg) 0%,var(--bg2) 100%);
  padding:6rem 2rem;
}
.chat-wrapper{
  max-width:900px;margin:0 auto;
  display:grid;grid-template-columns:1fr 1fr;gap:4rem;align-items:center;
}
.chat-info .section-sub{margin-bottom:2rem}
.chat-features{list-style:none;display:flex;flex-direction:column;gap:.8rem}
.chat-features li{
  display:flex;align-items:center;gap:.8rem;
  font-size:.9rem;color:var(--muted);
}
.chat-features li::before{
  content:'';width:8px;height:8px;border-radius:50%;flex-shrink:0;
  background:linear-gradient(135deg,var(--gold),var(--teal));
}

.chat-box{
  background:var(--card);
  border:1px solid var(--border);
  border-radius:20px;
  overflow:hidden;
  box-shadow:0 30px 80px rgba(0,0,0,0.5);
}
.chat-header{
  padding:1rem 1.2rem;
  background:var(--bg3);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:.8rem;
}
.chat-avatar{
  width:36px;height:36px;border-radius:50%;
  background:linear-gradient(135deg,var(--gold),var(--teal));
  display:flex;align-items:center;justify-content:center;
  font-size:1rem;flex-shrink:0;
}
.chat-agent-name{font-size:.9rem;font-weight:600}
.chat-status{font-size:.75rem;color:var(--teal);display:flex;align-items:center;gap:.4rem}
.chat-status::before{content:'';width:6px;height:6px;border-radius:50%;background:var(--teal);animation:pulse 1.5s infinite}
.chat-status-dot{width:6px;height:6px;border-radius:50%;background:var(--teal)}

.chat-messages{
  padding:1.2rem;
  display:flex;flex-direction:column;gap:.8rem;
  min-height:280px;max-height:280px;overflow-y:auto;
  scrollbar-width:thin;scrollbar-color:var(--border) transparent;
}
.msg{
  max-width:85%;border-radius:14px;
  padding:.7rem 1rem;font-size:.85rem;line-height:1.5;
  animation:msgIn .3s ease;
}
@keyframes msgIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.msg.bot{
  background:var(--bg3);color:var(--white);
  border-radius:14px 14px 14px 4px;
  border:1px solid var(--border);
  align-self:flex-start;
}
.msg.user{
  background:linear-gradient(135deg,var(--gold),#e8920a);
  color:#0A0F1C;font-weight:500;
  border-radius:14px 14px 4px 14px;
  align-self:flex-end;
}
.msg.typing{background:var(--bg3);border:1px solid var(--border);align-self:flex-start}
.typing-dots{display:flex;gap:4px;align-items:center;height:16px}
.typing-dots span{
  width:6px;height:6px;border-radius:50%;
  background:var(--muted);
  animation:typingBounce 1.2s infinite;
}
.typing-dots span:nth-child(2){animation-delay:.2s}
.typing-dots span:nth-child(3){animation-delay:.4s}
@keyframes typingBounce{0%,80%,100%{transform:translateY(0)}40%{transform:translateY(-6px)}}

.chat-input-area{
  padding:.8rem 1rem;
  border-top:1px solid var(--border);
  display:flex;gap:.6rem;align-items:center;
  background:var(--bg3);
}
.chat-input{
  flex:1;background:var(--bg);
  border:1px solid var(--border);border-radius:50px;
  padding:.6rem 1rem;color:var(--white);font-size:.85rem;
  font-family:'Inter',sans-serif;outline:none;
  transition:border-color .2s;
}
.chat-input:focus{border-color:var(--gold)}
.chat-input::placeholder{color:var(--muted)}
.chat-send{
  width:36px;height:36px;border-radius:50%;
  background:linear-gradient(135deg,var(--gold),#e8920a);
  border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  color:#0A0F1C;font-size:1rem;
  transition:transform .2s,box-shadow .2s;
  flex-shrink:0;
}
.chat-send:hover{transform:scale(1.1);box-shadow:0 4px 20px rgba(244,164,38,0.5)}

/* ── CANAUX ── */
#canaux{background:var(--bg2)}
.canaux-grid{
  display:grid;grid-template-columns:repeat(3,1fr);
  gap:1.5rem;margin-top:3rem;
}
.canal-card{
  border-radius:var(--r);padding:2.5rem 2rem;text-align:center;
  transition:transform .3s,box-shadow .3s;
  opacity:0;transform:translateY(30px);
}
.canal-card.visible{animation:cardIn .6s ease forwards}
.canal-card:hover{transform:translateY(-6px)}
.canal-telegram{
  background:linear-gradient(135deg,rgba(40,167,255,0.12),rgba(40,167,255,0.04));
  border:1px solid rgba(40,167,255,0.25);
}
.canal-whatsapp{
  background:linear-gradient(135deg,rgba(37,211,102,0.12),rgba(37,211,102,0.04));
  border:1px solid rgba(37,211,102,0.25);
}
.canal-web{
  background:linear-gradient(135deg,rgba(244,164,38,0.12),rgba(0,200,150,0.04));
  border:1px solid var(--border);
}
.canal-icon{font-size:3rem;margin-bottom:1rem;display:block}
.canal-name{font-size:1.2rem;font-weight:700;margin-bottom:.5rem}
.canal-desc{color:var(--muted);font-size:.9rem;line-height:1.6}
.canal-badge{
  display:inline-block;margin-top:1rem;
  padding:.3rem .8rem;border-radius:50px;
  font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:1px;
}
.badge-blue{background:rgba(40,167,255,.15);color:#28a7ff}
.badge-green{background:rgba(37,211,102,.15);color:#25d366}
.badge-gold{background:rgba(244,164,38,.15);color:var(--gold)}

/* ── CANAL ACTION & RIPPLE ── */
.canal-card{cursor:pointer;position:relative;overflow:hidden;user-select:none}
.canal-ripple{
  position:absolute;inset:0;border-radius:inherit;
  background:radial-gradient(circle at var(--rx,50%) var(--ry,50%),rgba(255,255,255,0.08) 0%,transparent 65%);
  opacity:0;transition:opacity .3s;pointer-events:none;
}
.canal-card:hover .canal-ripple{opacity:1}
.canal-card:hover{transform:translateY(-6px);box-shadow:0 24px 60px rgba(0,0,0,0.45)}
.canal-action{display:flex;align-items:center;justify-content:space-between;margin-top:1.2rem;flex-wrap:wrap;gap:.5rem}
.canal-btn{
  font-size:.82rem;font-weight:700;padding:.4rem 1rem;
  border-radius:50px;cursor:pointer;letter-spacing:.5px;
  transition:transform .2s,opacity .2s;
}
.canal-btn-blue{background:rgba(40,167,255,.18);color:#28a7ff;border:1px solid rgba(40,167,255,.35)}
.canal-btn-green{background:rgba(37,211,102,.18);color:#25d366;border:1px solid rgba(37,211,102,.35)}
.canal-btn-gold{background:rgba(244,164,38,.18);color:var(--gold);border:1px solid rgba(244,164,38,.35)}
.canal-card:hover .canal-btn{transform:translateX(5px)}

/* ── MODAL ── */
.canal-modal-overlay{
  position:fixed;inset:0;z-index:999;
  background:rgba(0,0,0,0);
  display:flex;align-items:center;justify-content:center;padding:1.5rem;
  pointer-events:none;transition:background .3s;
}
.canal-modal-overlay.open{background:rgba(0,0,0,0.72);pointer-events:all;backdrop-filter:blur(8px)}
.canal-modal-box{
  background:var(--card);border:1px solid var(--border);border-radius:24px;
  padding:2.5rem 2rem;max-width:420px;width:100%;text-align:center;position:relative;
  transform:translateY(32px) scale(0.94);opacity:0;
  transition:transform .38s cubic-bezier(.34,1.56,.64,1),opacity .3s;
  box-shadow:0 40px 120px rgba(0,0,0,0.65);
}
.canal-modal-overlay.open .canal-modal-box{transform:translateY(0) scale(1);opacity:1}
.canal-modal-close{
  position:absolute;top:1rem;right:1rem;
  background:rgba(255,255,255,0.05);border:1px solid var(--border);
  color:var(--muted);width:32px;height:32px;border-radius:50%;
  cursor:pointer;font-size:.85rem;transition:all .2s;
  display:flex;align-items:center;justify-content:center;line-height:1;
}
.canal-modal-close:hover{background:rgba(255,255,255,0.1);color:var(--white)}
.modal-icon{font-size:3.5rem;margin-bottom:1rem;line-height:1}
.modal-title{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;margin-bottom:.6rem}
.modal-desc{color:var(--muted);font-size:.9rem;line-height:1.6;margin-bottom:1.5rem}
.modal-steps{display:flex;flex-direction:column;gap:.6rem;margin-bottom:1.8rem;text-align:left}
.modal-step{
  display:flex;align-items:center;gap:.8rem;
  background:var(--bg3);border:1px solid var(--border);
  border-radius:10px;padding:.6rem .9rem;font-size:.84rem;color:var(--white);
}
.modal-step-num{
  width:22px;height:22px;border-radius:50%;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;
}
.step-blue{background:rgba(40,167,255,.25);color:#28a7ff}
.step-green{background:rgba(37,211,102,.25);color:#25d366}
.step-gold{background:rgba(244,164,38,.25);color:var(--gold)}
.modal-cta-btn{
  display:block;width:100%;padding:.85rem;border-radius:50px;
  font-weight:700;font-size:1rem;text-decoration:none;
  transition:transform .2s,filter .2s;margin-bottom:.8rem;
  border:none;cursor:pointer;text-align:center;
}
.cta-blue{background:linear-gradient(135deg,#28a7ff,#0066cc);color:#fff;box-shadow:0 4px 20px rgba(40,167,255,.35)}
.cta-green{background:linear-gradient(135deg,#25d366,#128c3e);color:#fff;box-shadow:0 4px 20px rgba(37,211,102,.35)}
.cta-gold{background:linear-gradient(135deg,var(--gold),#e8920a);color:#0A0F1C;box-shadow:0 4px 20px rgba(244,164,38,.35)}
.modal-cta-btn:hover{transform:translateY(-2px);filter:brightness(1.1)}
.modal-cancel{
  background:none;border:none;color:var(--muted);
  font-size:.85rem;cursor:pointer;padding:.4rem 1rem;transition:color .2s;
}
.modal-cancel:hover{color:var(--white)}

/* ── FOOTER ── */
footer{
  position:relative;z-index:1;
  background:var(--bg3);
  border-top:1px solid var(--border);
  padding:3rem 2rem 2rem;
  text-align:center;
}
.footer-logo{
  font-family:'Playfair Display',serif;
  font-size:1.8rem;font-weight:900;
  background:linear-gradient(135deg,var(--gold),var(--teal));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
  background-clip:text;margin-bottom:1rem;display:block;
}
.footer-desc{color:var(--muted);font-size:.9rem;max-width:400px;margin:0 auto 1.5rem}
.footer-copy{color:var(--muted);font-size:.8rem;border-top:1px solid var(--border);padding-top:1.5rem;margin-top:1.5rem}

/* ── ANIMATIONS GLOBALES ── */
@keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeDown{from{opacity:0;transform:translateY(-16px)}to{opacity:1;transform:translateY(0)}}

/* ── GLOW ORBS ── */
.orb{
  position:absolute;border-radius:50%;filter:blur(80px);pointer-events:none;z-index:0;
}
.orb1{width:400px;height:400px;background:rgba(244,164,38,0.07);top:-100px;right:-100px}
.orb2{width:300px;height:300px;background:rgba(0,200,150,0.06);bottom:100px;left:-80px}

/* ── RESPONSIVE ── */
@media(max-width:768px){
  .chat-wrapper{grid-template-columns:1fr}
  .canaux-grid{grid-template-columns:1fr}
  .hero-stats{gap:1.5rem}
  .nav-links{display:none}
}

@media(prefers-reduced-motion:reduce){
  *{animation-duration:.01ms !important;transition-duration:.01ms !important}
}
</style>
</head>
<body>

<canvas id="particles-canvas"></canvas>

<!-- NAV -->
<nav id="navbar">
  <span class="nav-logo">VisitCI</span>
  <ul class="nav-links">
    <li><a href="#features">Fonctionnalités</a></li>
    <li><a href="#categories">Explorer</a></li>
    <li><a href="#chat-section">Assistant IA</a></li>
    <li><a href="#canaux">Canaux</a></li>
  </ul>
  <a href="#chat-section" class="nav-cta">Parler à l'IA</a>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="orb orb1"></div>
  <div class="orb orb2"></div>
  <div class="hero-eyebrow"><span></span>Assistant IA touristique — Côte d'Ivoire</div>
  <h1 class="hero-title">
    <span class="line1">Découvrez la</span>
    <span class="line2">Côte d'Ivoire</span>
  </h1>
  <p class="hero-sub">Votre guide intelligent disponible 24h/24 sur Telegram, WhatsApp et ici même. Restaurants, hôtels, activités, transports — tout ce qu'il vous faut.</p>
  <div class="hero-actions">
    <a href="#chat-section" class="btn-primary">
      <span>💬</span> Poser une question
    </a>
    <a href="#categories" class="btn-secondary">
      <span>🗺️</span> Explorer la carte
    </a>
  </div>
  <div class="hero-stats">
    <div class="stat">
      <div class="stat-num" data-target="500">0</div>
      <div class="stat-label">Lieux référencés</div>
    </div>
    <div class="stat">
      <div class="stat-num" data-target="8">0</div>
      <div class="stat-label">Catégories</div>
    </div>
    <div class="stat">
      <div class="stat-num" data-target="3">0</div>
      <div class="stat-label">Canaux disponibles</div>
    </div>
  </div>
  <div class="scroll-indicator">
    <div class="scroll-line"></div>
    <span>Défiler</span>
  </div>
</section>

<!-- FEATURES -->
<section id="features">
  <div class="container">
    <div class="features-header">
      <div class="section-eyebrow">Pourquoi VisitCI ?</div>
      <h2 class="section-title">Un guide <span>intelligent</span> qui vous comprend</h2>
    </div>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon">🤖</div>
        <div class="feature-title">IA ultra-rapide avec Groq</div>
        <div class="feature-desc">Réponses instantanées grâce à Groq LLaMA. Posez vos questions en français, anglais ou même en dioula.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">📍</div>
        <div class="feature-title">Données locales vérifiées</div>
        <div class="feature-desc">Base de données curatée avec horaires, tarifs, coordonnées GPS et avis réels des visiteurs.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">💬</div>
        <div class="feature-title">Multi-canal natif</div>
        <div class="feature-desc">Telegram, WhatsApp ou ce site — même cerveau IA, même expérience, sur tous vos appareils.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🕐</div>
        <div class="feature-title">Disponible 24h/24</div>
        <div class="feature-desc">Arrivée tardive ? Besoin d'une pharmacie de nuit ? L'assistant ne dort jamais et connaît les horaires.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">⭐</div>
        <div class="feature-title">Avis et recommandations</div>
        <div class="feature-desc">Notes et commentaires de vrais touristes. Laissez vos avis directement dans la conversation.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🔒</div>
        <div class="feature-title">Mémoire de conversation</div>
        <div class="feature-desc">L'IA se souvient de vos préférences durant la session pour des recommandations personnalisées.</div>
      </div>
    </div>
  </div>
</section>

<!-- CATÉGORIES -->
<section id="categories">
  <div class="container">
    <div class="section-eyebrow">Explorer</div>
    <h2 class="section-title">Tout ce dont vous <span>avez besoin</span></h2>
    <p class="section-sub">De l'attiéké au bord de mer aux hôtels de luxe du Plateau — retrouvez tout en un seul endroit.</p>
    <div class="categories-grid">
      <div class="cat-card" onclick="askAbout('restaurants et maquis')">
        <span class="cat-icon">🍽️</span>
        <div class="cat-name">Restaurants & Maquis</div>
        <div class="cat-count">Cuisine locale & internationale</div>
      </div>
      <div class="cat-card" onclick="askAbout('hôtels et résidences')">
        <span class="cat-icon">🏨</span>
        <div class="cat-name">Hôtels & Résidences</div>
        <div class="cat-count">Luxe à économique</div>
      </div>
      <div class="cat-card" onclick="askAbout('activités et loisirs')">
        <span class="cat-icon">🎭</span>
        <div class="cat-name">Activités & Loisirs</div>
        <div class="cat-count">Sorties & culture</div>
      </div>
      <div class="cat-card" onclick="askAbout('transports disponibles')">
        <span class="cat-icon">🚖</span>
        <div class="cat-name">Transports</div>
        <div class="cat-count">Taxi, woro-woro, bateau</div>
      </div>
      <div class="cat-card" onclick="askAbout('plages et sites touristiques')">
        <span class="cat-icon">🏖️</span>
        <div class="cat-name">Plages & Sites</div>
        <div class="cat-count">Nature & patrimoine</div>
      </div>
      <div class="cat-card" onclick="askAbout('pharmacies et hôpitaux')">
        <span class="cat-icon">🏥</span>
        <div class="cat-name">Santé</div>
        <div class="cat-count">Pharmacies & cliniques</div>
      </div>
      <div class="cat-card" onclick="askAbout('banques et distributeurs ATM')">
        <span class="cat-icon">🏦</span>
        <div class="cat-name">Banques & Change</div>
        <div class="cat-count">ATM & mobile money</div>
      </div>
      <div class="cat-card" onclick="askAbout('marchés et centres commerciaux')">
        <span class="cat-icon">🛍️</span>
        <div class="cat-name">Marchés & Shopping</div>
        <div class="cat-count">Artisanat & souvenirs</div>
      </div>
    </div>
  </div>
</section>

<!-- CHAT SECTION -->
<section id="chat-section">
  <div class="chat-wrapper">
    <div class="chat-info">
      <div class="section-eyebrow">Assistant IA</div>
      <h2 class="section-title">Posez <span>toutes vos questions</span></h2>
      <p class="section-sub">Notre IA connaît Abidjan mieux qu'un guide local. Demandez-lui n'importe quoi.</p>
      <ul class="chat-features">
        <li>Où manger de l'attiéké ce soir ?</li>
        <li>Hôtel avec piscine moins de 50 000 FCFA ?</li>
        <li>Comment aller de Cocody à la Riviera ?</li>
        <li>Pharmacie ouverte la nuit à Yopougon ?</li>
        <li>Meilleure boîte de nuit ce weekend ?</li>
      </ul>
    </div>
    <div class="chat-box">
      <div class="chat-header">
        <div class="chat-avatar">🌍</div>
        <div>
          <div class="chat-agent-name">VisitCI IA</div>
          <div class="chat-status">En ligne</div>
        </div>
      </div>
      <div class="chat-messages" id="chat-messages">
        <div class="msg bot">Bonjour ! Je suis votre guide touristique pour la Côte d'Ivoire 🇨🇮 Que souhaitez-vous découvrir ?</div>
      </div>
      <div class="chat-input-area">
        <input type="text" class="chat-input" id="chat-input" placeholder="Ex: Meilleur restaurant à Cocody..." />
        <button class="chat-send" onclick="sendMessage()" id="chat-send-btn">➤</button>
      </div>
    </div>
  </div>
</section>

<!-- CANAUX -->
<section id="canaux">
  <div class="container">
    <div style="text-align:center;margin-bottom:4rem">
      <div class="section-eyebrow">Partout avec vous</div>
      <h2 class="section-title">Choisissez votre <span>canal préféré</span></h2>
    </div>
    <div class="canaux-grid">

      <!-- Telegram -->
      <div class="canal-card canal-telegram" onclick="openCanal('telegram')" role="button" tabindex="0" aria-label="Ouvrir Telegram">
        <div class="canal-ripple"></div>
        <span class="canal-icon">✈️</span>
        <div class="canal-name">Telegram</div>
        <div class="canal-desc">Discutez avec notre bot directement depuis Telegram. Rapide, sécurisé et sans publicité.</div>
        <div class="canal-action">
          <span class="canal-badge badge-blue">@VisitCI_bot</span>
          <span class="canal-btn canal-btn-blue">Ouvrir ›</span>
        </div>
      </div>

      <!-- WhatsApp -->
      <div class="canal-card canal-whatsapp" onclick="openCanal('whatsapp')" role="button" tabindex="0" aria-label="Ouvrir WhatsApp">
        <div class="canal-ripple"></div>
        <span class="canal-icon">📱</span>
        <div class="canal-name">WhatsApp</div>
        <div class="canal-desc">Envoyez un message WhatsApp et obtenez des recommandations instantanément.</div>
        <div class="canal-action">
          <span class="canal-badge badge-green">WhatsApp Business</span>
          <span class="canal-btn canal-btn-green">Ouvrir ›</span>
        </div>
      </div>

      <!-- Web -->
      <div class="canal-card canal-web" onclick="openCanal('web')" role="button" tabindex="0" aria-label="Utiliser le chat web">
        <div class="canal-ripple"></div>
        <span class="canal-icon">🌐</span>
        <div class="canal-name">Application Web</div>
        <div class="canal-desc">Utilisez directement ce site pour une expérience complète avec cartes et photos.</div>
        <div class="canal-action">
          <span class="canal-badge badge-gold">Vous êtes ici</span>
          <span class="canal-btn canal-btn-gold">Commencer ›</span>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- MODAL CANAL -->
<div id="canal-modal" class="canal-modal-overlay" onclick="closeModal(event)" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="canal-modal-box">
    <button class="canal-modal-close" onclick="closeModal()" aria-label="Fermer">✕</button>
    <div id="modal-icon" class="modal-icon"></div>
    <h3 id="modal-title" class="modal-title"></h3>
    <p id="modal-desc" class="modal-desc"></p>
    <div id="modal-steps" class="modal-steps"></div>
    <a id="modal-cta" class="modal-cta-btn" href="#" target="_blank" rel="noopener">Continuer</a>
    <button class="modal-cancel" onclick="closeModal()">Annuler</button>
  </div>
</div>

<!-- FOOTER -->
<footer>
  <span class="footer-logo">VisitCI</span>
  <p class="footer-desc">Votre compagnon de voyage intelligent pour découvrir toutes les merveilles de la Côte d'Ivoire.</p>
  <p class="footer-copy">© <?= date('Y') ?> VisitCI — Propulsé par Groq IA &amp; développé avec ❤️ en Côte d'Ivoire</p>
</footer>

<script>
// ── PARTICULES ──
const canvas = document.getElementById('particles-canvas');
const ctx = canvas.getContext('2d');
let particles = [];

function resize() {
  canvas.width = window.innerWidth;
  canvas.height = window.innerHeight;
}
resize();
window.addEventListener('resize', resize);

class Particle {
  constructor() { this.reset(); }
  reset() {
    this.x = Math.random() * canvas.width;
    this.y = Math.random() * canvas.height;
    this.size = Math.random() * 2 + 0.5;
    this.speedX = (Math.random() - 0.5) * 0.4;
    this.speedY = (Math.random() - 0.5) * 0.4;
    this.opacity = Math.random() * 0.5 + 0.1;
    this.color = Math.random() > 0.5 ? '#F4A426' : '#00C896';
  }
  update() {
    this.x += this.speedX;
    this.y += this.speedY;
    if (this.x < 0 || this.x > canvas.width || this.y < 0 || this.y > canvas.height) this.reset();
  }
  draw() {
    ctx.beginPath();
    ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
    ctx.fillStyle = this.color;
    ctx.globalAlpha = this.opacity;
    ctx.fill();
    ctx.globalAlpha = 1;
  }
}

for (let i = 0; i < 80; i++) particles.push(new Particle());

function connectParticles() {
  for (let i = 0; i < particles.length; i++) {
    for (let j = i + 1; j < particles.length; j++) {
      const dx = particles[i].x - particles[j].x;
      const dy = particles[i].y - particles[j].y;
      const dist = Math.sqrt(dx*dx + dy*dy);
      if (dist < 120) {
        ctx.beginPath();
        ctx.moveTo(particles[i].x, particles[i].y);
        ctx.lineTo(particles[j].x, particles[j].y);
        ctx.strokeStyle = '#F4A426';
        ctx.globalAlpha = (1 - dist/120) * 0.08;
        ctx.lineWidth = 0.5;
        ctx.stroke();
        ctx.globalAlpha = 1;
      }
    }
  }
}

function animateParticles() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  particles.forEach(p => { p.update(); p.draw(); });
  connectParticles();
  requestAnimationFrame(animateParticles);
}
animateParticles();

// ── SCROLL ANIMATIONS ──
const observer = new IntersectionObserver((entries) => {
  entries.forEach((entry, i) => {
    if (entry.isIntersecting) {
      setTimeout(() => entry.target.classList.add('visible'), i * 80);
    }
  });
}, { threshold: 0.1 });

document.querySelectorAll('.feature-card, .cat-card, .canal-card').forEach(el => observer.observe(el));

// ── COMPTEURS ANIMÉS ──
function animateCounter(el) {
  const target = +el.dataset.target;
  let current = 0;
  const step = target / 60;
  const timer = setInterval(() => {
    current += step;
    if (current >= target) { el.textContent = target + '+'; clearInterval(timer); return; }
    el.textContent = Math.floor(current);
  }, 25);
}
const statsObserver = new IntersectionObserver((entries) => {
  entries.forEach(e => { if (e.isIntersecting) { animateCounter(e.target); statsObserver.unobserve(e.target); } });
}, { threshold: 0.5 });
document.querySelectorAll('[data-target]').forEach(el => statsObserver.observe(el));

// ── NAVBAR SCROLL ──
window.addEventListener('scroll', () => {
  const nav = document.getElementById('navbar');
  nav.style.background = window.scrollY > 50 ? 'rgba(10,15,28,0.95)' : 'rgba(10,15,28,0.7)';
});

// ── CHAT IA ──
const chatMessages = document.getElementById('chat-messages');
const chatInput = document.getElementById('chat-input');
let conversationHistory = [];

function addMessage(text, type) {
  const div = document.createElement('div');
  div.className = 'msg ' + type;
  div.textContent = text;
  chatMessages.appendChild(div);
  chatMessages.scrollTop = chatMessages.scrollHeight;
  return div;
}

function addTyping() {
  const div = document.createElement('div');
  div.className = 'msg typing';
  div.id = 'typing-indicator';
  div.innerHTML = '<div class="typing-dots"><span></span><span></span><span></span></div>';
  chatMessages.appendChild(div);
  chatMessages.scrollTop = chatMessages.scrollHeight;
}

function removeTyping() {
  const t = document.getElementById('typing-indicator');
  if (t) t.remove();
}

async function sendMessage() {
  const text = chatInput.value.trim();
  if (!text) return;
  chatInput.value = '';
  addMessage(text, 'user');
  conversationHistory.push({ role: 'user', content: text });
  addTyping();
  document.getElementById('chat-send-btn').disabled = true;

  try {
    const response = await fetch('/api/chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text, history: conversationHistory })
    });
    const data = await response.json();
    removeTyping();
    const reply = data.reply || "Désolé, je n'ai pas pu traiter votre demande.";
    addMessage(reply, 'bot');
    conversationHistory.push({ role: 'assistant', content: reply });
  } catch (e) {
    removeTyping();
    console.error('Erreur fetch:', e);
    addMessage("❌ Erreur: " + e.message, 'bot');
  }
  document.getElementById('chat-send-btn').disabled = false;
}

function askAbout(topic) {
  chatInput.value = 'Quels sont les meilleurs ' + topic + ' à Abidjan ?';
  document.getElementById('chat-section').scrollIntoView({ behavior: 'smooth' });
  setTimeout(() => sendMessage(), 600);
}

chatInput.addEventListener('keydown', e => { if (e.key === 'Enter') sendMessage(); });

// ── MESSAGE DE BIENVENUE DELAY ──
setTimeout(() => {
  addMessage('Vous pouvez aussi cliquer sur une catégorie pour explorer directement 👆', 'bot');
}, 2000);

// ── RIPPLE EFFECT SUR LES CARTES CANAL ──
document.querySelectorAll('.canal-card').forEach(card => {
  card.addEventListener('mousemove', e => {
    const r = card.getBoundingClientRect();
    const x = ((e.clientX - r.left) / r.width * 100).toFixed(1);
    const y = ((e.clientY - r.top)  / r.height * 100).toFixed(1);
    card.style.setProperty('--rx', x + '%');
    card.style.setProperty('--ry', y + '%');
  });
  card.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); card.click(); }
  });
});

// ── CANAL CONFIGS ────────────────────────────────────────────
const CANAL_CONFIG = {
  telegram: {
    icon: '✈️',
    title: 'Ouvrir Telegram',
    desc: 'Vous allez être redirigé vers notre bot Telegram. Cherchez @VisitCI_bot ou cliquez sur le bouton ci-dessous.',
    ctaText: 'Ouvrir @VisitCI_bot',
    ctaClass: 'cta-blue',
    ctaHref: 'https://t.me/VisitCI_bot',
    stepClass: 'step-blue',
    steps: [
      'Installez Telegram si ce n\'est pas déjà fait',
      'Cliquez sur "Ouvrir @VisitCI_bot" ci-dessous',
      'Tapez /start pour commencer la conversation',
    ],
  },
  whatsapp: {
    icon: '📱',
    title: 'Ouvrir WhatsApp',
    desc: 'Démarrez une conversation WhatsApp avec notre assistant IA touristique. Disponible 24h/24.',
    ctaText: 'Ouvrir WhatsApp',
    ctaClass: 'cta-green',
    // Remplacez 22500000000 par votre vrai numéro WhatsApp Business
    ctaHref: 'https://wa.me/22500000000?text=Bonjour%20VisitCI%2C%20je%20cherche%20des%20infos%20touristiques%20%F0%9F%87%A8%F0%9F%87%AE',
    stepClass: 'step-green',
    steps: [
      'WhatsApp doit être installé sur votre téléphone',
      'Cliquez sur "Ouvrir WhatsApp" ci-dessous',
      'Le message de démarrage est pré-rempli, envoyez-le !',
    ],
  },
  web: {
    icon: '🌐',
    title: 'Chat sur le site',
    desc: 'Utilisez le chat intégré directement sur cette page pour poser toutes vos questions touristiques.',
    ctaText: 'Aller au chat ›',
    ctaClass: 'cta-gold',
    ctaHref: '#chat-section',
    stepClass: 'step-gold',
    steps: [
      'Descendez jusqu\'à la section "Assistant IA"',
      'Tapez votre question dans le champ de saisie',
      'L\'IA vous répond en moins de 3 secondes !',
    ],
  },
};

function openCanal(type) {
  const cfg = CANAL_CONFIG[type];
  if (!cfg) return;

  // Remplir le modal
  document.getElementById('modal-icon').textContent  = cfg.icon;
  document.getElementById('modal-title').textContent = cfg.title;
  document.getElementById('modal-desc').textContent  = cfg.desc;

  // Étapes
  const stepsEl = document.getElementById('modal-steps');
  stepsEl.innerHTML = cfg.steps.map((s, i) =>
    `<div class="modal-step">
      <span class="modal-step-num ${cfg.stepClass}">${i+1}</span>
      <span>${s}</span>
    </div>`
  ).join('');

  // Bouton CTA
  const cta = document.getElementById('modal-cta');
  cta.textContent = cfg.ctaText;
  cta.className   = 'modal-cta-btn ' + cfg.ctaClass;
  cta.href        = cfg.ctaHref;

  // Scroll interne (web) vs lien externe
  if (type === 'web') {
    cta.removeAttribute('target');
    cta.onclick = (e) => {
      e.preventDefault();
      closeModal();
      setTimeout(() => {
        document.getElementById('chat-section').scrollIntoView({ behavior: 'smooth' });
        setTimeout(() => chatInput.focus(), 600);
      }, 250);
    };
  } else {
    cta.setAttribute('target', '_blank');
    cta.onclick = null;
  }

  // Afficher
  const overlay = document.getElementById('canal-modal');
  overlay.setAttribute('aria-hidden', 'false');
  overlay.classList.add('open');
  document.body.style.overflow = 'hidden';

  // Fermer avec Escape
  document.addEventListener('keydown', escapeModal);
}

function closeModal(event) {
  if (event && event.target !== document.getElementById('canal-modal')) return;
  const overlay = document.getElementById('canal-modal');
  overlay.classList.remove('open');
  overlay.setAttribute('aria-hidden', 'true');
  document.body.style.overflow = '';
  document.removeEventListener('keydown', escapeModal);
}

function escapeModal(e) {
  if (e.key === 'Escape') closeModal();
}
</script>
</body>
</html>
