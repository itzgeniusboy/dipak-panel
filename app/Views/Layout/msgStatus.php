<?php if (session()->getFlashdata('msgDanger')) : ?>
  <div class="cyber-alert cyber-alert-danger" role="alert" aria-live="assertive">
    <div class="cyber-alert-content">
      <i class="fas fa-circle-xmark cyber-pulse"></i>
      <?= session()->getFlashdata('msgDanger') ?>
      <button type="button" class="cyber-alert-close" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
  </div>

<?php elseif (session()->getFlashdata('msgSuccess')) : ?>
  <div class="cyber-alert cyber-alert-success" role="alert" aria-live="polite">
    <div class="cyber-alert-content">
      <i class="fas fa-circle-check cyber-pulse"></i>
      <?= session()->getFlashdata('msgSuccess') ?>
      <button type="button" class="cyber-alert-close" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
  </div>

<?php elseif (session()->getFlashdata('msgWarning')) : ?>
  <div class="cyber-alert cyber-alert-warning" role="alert" aria-live="polite">
    <div class="cyber-alert-content">
      <i class="fas fa-triangle-exclamation cyber-pulse"></i>
      <?= session()->getFlashdata('msgWarning') ?>
      <button type="button" class="cyber-alert-close" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
  </div>

<?php else : ?>
  <?php if (session()->has('userid')) : ?>
    <?php if (isset($messages)) : ?>
      <div class="cyber-alert cyber-alert-<?= $messages[1] ?>" role="alert" aria-live="polite">
        <div class="cyber-alert-content">
          <i class="fas fa-circle-info cyber-pulse"></i>
          <?= $messages[0] ?>
          <button type="button" class="cyber-alert-close" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
      </div>
    <?php else : ?>
      <div class="cyber-alert cyber-alert-primary" role="alert" aria-live="polite">
        <div class="cyber-alert-content">
          <i class="fas fa-circle-user cyber-pulse"></i>
          Welcome <?= getName($user) ?>
          <button type="button" class="cyber-alert-close" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
      </div>
    <?php endif; ?>
  <?php else : ?>
    <div class="cyber-alert cyber-alert-primary" role="alert" aria-live="polite">
      <div class="cyber-alert-content">
        <i class="fas fa-circle-question cyber-pulse"></i>
        Welcome Stranger
        <button type="button" class="cyber-alert-close" aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<style>
  :root{
    --brand1:#4facfe;   /* first theme blue */
    --brand2:#00f2fe;   /* first theme cyan */
    --glass-bg: rgba(255,255,255,.14);
    --glass-border: rgba(255,255,255,.28);
  }

  /* GLASS ALERT (First-theme look) */
  .cyber-alert{
    border:1px solid var(--glass-border);
    border-radius:12px;
    padding: .85rem 1rem;
    margin-bottom:1rem;
    position:relative;
    background: linear-gradient(135deg, rgba(79,172,254,.12), rgba(0,242,254,.10));
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    box-shadow: 0 8px 22px rgba(0,0,0,.25);
    animation: cyberFadeIn .45s ease-out both;
  }
  .cyber-alert-content{
    display:flex; align-items:center; gap:.75rem; color:#fff;
  }
  .cyber-alert i{ font-size:1.1rem; }

  /* Variants (border/text tint) */
  .cyber-alert-primary{ border-color: var(--brand2); color:#e9fcff; }
  .cyber-alert-success{ border-color:#32d296; color:#bff4db; }
  .cyber-alert-warning{ border-color:#ffcc00; color:#ffeaa6; }
  .cyber-alert-danger { border-color:#ff4c4c; color:#ffc7c7; }

  /* Close button */
  .cyber-alert-close{
    background:transparent; border:none; color:inherit; margin-left:auto; cursor:pointer;
    padding:.25rem; line-height:1;
    opacity:.9; transition:.2s;
  }
  .cyber-alert-close:hover{ opacity:1; transform:scale(1.05); }

  /* Pulse for leading icon */
  @keyframes pulse { 0%{opacity:.85} 50%{opacity:1} 100%{opacity:.85} }
  .cyber-pulse{ animation:pulse 2s ease-in-out infinite; }

  /* In & Out */
  @keyframes cyberFadeIn { from{opacity:0; transform:translateY(-8px)} to{opacity:1; transform:translateY(0)} }
  @keyframes cyberFadeOut{ from{opacity:1; transform:translateY(0)} to{opacity:0; transform:translateY(-8px)} }
</style>

<script>
  (function(){
    // Manual close
    document.querySelectorAll('.cyber-alert-close').forEach(btn=>{
      btn.addEventListener('click', function(){
        const box = this.closest('.cyber-alert');
        box.style.animation = 'cyberFadeOut .45s ease-out forwards';
        setTimeout(()=> box.remove(), 450);
      });
    });

    // Auto-hide after 5s (pause on hover)
    document.querySelectorAll('.cyber-alert').forEach(box=>{
      let timer = setTimeout(()=>dismiss(box), 5000);
      box.addEventListener('mouseenter', ()=> clearTimeout(timer));
      box.addEventListener('mouseleave', ()=> timer = setTimeout(()=>dismiss(box), 1500));
    });

    function dismiss(el){
      if(!el) return;
      el.style.animation = 'cyberFadeOut .45s ease-out forwards';
      setTimeout(()=> el.remove(), 450);
    }
  })();
</script>