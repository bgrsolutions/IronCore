<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Repair Confirmation</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 1rem; background: #f6f7fb; }
        .card { max-width: 720px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 1rem; }
        h1 { font-size: 1.4rem; }
        .btn { padding: 0.9rem 1.2rem; border: 0; border-radius: 8px; font-size: 1.1rem; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: #e5e7eb; color: #111827; }
        .actions { display: flex; gap: .5rem; flex-wrap: wrap; }
        canvas { width: 100%; height: 220px; border: 2px solid #cbd5e1; border-radius: 8px; touch-action: none; background: #fff; }
        input, select, textarea { width: 100%; padding: .7rem; font-size: 1rem; margin: .3rem 0 1rem; }
        .success { color: #047857; font-weight: bold; }
        .error { color: #b91c1c; font-weight: bold; }
    </style>
</head>
<body>
<div class="card">
    <h1>{{ $token->purpose === 'repair_pickup_signature' ? 'Pickup Confirmation Signature' : 'Intake Signature' }}</h1>
    <p><strong>Repair:</strong> R-{{ $repair->id }} | {{ $repair->device_brand }} {{ $repair->device_model }}</p>
    <p><strong>Customer:</strong> {{ $repair->customer->name ?? 'N/A' }}</p>
    <p><strong>Issue:</strong> {{ $repair->reported_issue }}</p>

    @if($success)
        <p class="success">Done. Signature captured successfully.</p>
        @if($token->purpose === 'repair_pickup_signature')
            @if($feedbackToken)
            <form method="POST" action="{{ route('public.repairs.feedback', $feedbackToken->token) }}">
                @csrf
                <h2>Optional feedback</h2>
                <label>Rating (1-5)</label>
                <input type="number" min="1" max="5" name="rating" required>
                <label>Comment</label>
                <textarea name="comment" rows="3"></textarea>
                <button class="btn btn-primary" type="submit">Submit feedback</button>
            </form>
            @endif
        @endif
    @else
        @if($error)
            <p class="error">{{ $error }}</p>
        @endif

        <form id="sign-form" method="POST" action="{{ route('public.repairs.sign', $token->token) }}">
            @csrf
            <label>Signer name</label>
            <input type="text" name="signer_name" maxlength="120" placeholder="Full name" />
            <label>Signature</label>
            <canvas id="pad"></canvas>
            <input type="hidden" name="signature" id="signature" />
            <div class="actions">
                <button type="button" class="btn btn-secondary" id="clear-btn">Clear</button>
                <button type="submit" class="btn btn-primary">Submit Signature</button>
            </div>
        </form>
    @endif
</div>
<script>
const canvas = document.getElementById('pad');
if (canvas) {
  const ctx = canvas.getContext('2d');
  const ratio = window.devicePixelRatio || 1;
  canvas.width = canvas.offsetWidth * ratio;
  canvas.height = canvas.offsetHeight * ratio;
  ctx.scale(ratio, ratio);
  ctx.lineWidth = 2;
  ctx.lineCap = 'round';
  let drawing = false;
  let lastX = 0, lastY = 0;
  const pos = (e) => {
    const r = canvas.getBoundingClientRect();
    const p = e.touches ? e.touches[0] : e;
    return { x: p.clientX - r.left, y: p.clientY - r.top };
  };
  const start = (e) => { drawing = true; const p = pos(e); lastX = p.x; lastY = p.y; };
  const draw = (e) => {
    if (!drawing) return;
    e.preventDefault();
    const p = pos(e);
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    lastX = p.x; lastY = p.y;
  };
  const end = () => drawing = false;
  canvas.addEventListener('mousedown', start); canvas.addEventListener('mousemove', draw);
  window.addEventListener('mouseup', end);
  canvas.addEventListener('touchstart', start, {passive:false}); canvas.addEventListener('touchmove', draw, {passive:false});
  canvas.addEventListener('touchend', end);
  document.getElementById('clear-btn')?.addEventListener('click', () => ctx.clearRect(0, 0, canvas.width, canvas.height));
  document.getElementById('sign-form')?.addEventListener('submit', (e) => {
      const data = canvas.toDataURL('image/png');
      if (data.length < 200) {
          e.preventDefault();
          alert('Please provide a signature.');
          return;
      }
      document.getElementById('signature').value = data;
  });
}
</script>
</body>
</html>
