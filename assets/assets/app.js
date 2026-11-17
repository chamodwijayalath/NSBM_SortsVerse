/* NSBM SportsVerse front-end – talks to api.php */
const $ = (id) => document.getElementById(id);
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

let S = null; // bootstrap data
const tabNames = ['home', 'clubs', 'matches', 'tournaments', 'leaderboard', 'facilities'];

/* ---------------------------------------------------------------- API */
async function api(action, body, method) {
  const post = method === 'POST' || body;
  const url = post ? 'api.php' : 'api.php?action=' + action + (body ? '' : '');
  const res = await fetch(post ? 'api.php' : url, post
    ? { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' }, body: JSON.stringify({ action, ...body }), credentials: 'same-origin' }
    : { credentials: 'same-origin' });
  let data = {};
  try { data = await res.json(); } catch (e) { data = { ok: false, error: 'Server error.' }; }
  return data;
}
async function load() {
  const d = await fetch('api.php?action=bootstrap', { credentials: 'same-origin' }).then((r) => r.json());
  if (!d.ok) { showToast(d.error || 'Could not load data'); return; }
  S = d;
  renderAll();
}

/* ---------------------------------------------------------------- helpers */
const segBtn = (o, on) => `<button role="tab" aria-selected="${on}" data-v="${esc(o)}" class="btn px-4 py-1.5 rounded-lg text-sm ${on ? 'bg-em text-white' : 'text-slate-300 hover:text-white'}">${esc(o)}</button>`;
const chipBtn = (o, on) => `<button data-v="${esc(o)}" aria-pressed="${on}" class="btn shrink-0 whitespace-nowrap rounded-full border px-4 py-1.5 text-sm ${on ? 'bg-em text-white border-em' : 'bg-card text-slate-300 border-line hover:border-mint'}">${esc(o)}</button>`;
const bind = (id, fn) => $(id).addEventListener('click', (e) => { const b = e.target.closest('button[data-v]'); if (b) fn(b.dataset.v); });
let tt;
function showToast(m) {
  const t = $('gtoast'); t.textContent = m;
  t.classList.remove('opacity-0', 'translate-y-4');
  clearTimeout(tt); tt = setTimeout(() => t.classList.add('opacity-0', 'translate-y-4'), 2800);
}
const sportCls = { Rugby: 'bg-lime-400/15 text-lime-300', Football: 'bg-emerald-400/15 text-emerald-300', Cricket: 'bg-sky-400/15 text-sky-300', Basketball: 'bg-orange-400/15 text-orange-300', Badminton: 'bg-yellow-400/15 text-yellow-300' };
const sportIcon = { Rugby: '🏉', Football: '⚽', Cricket: '🏏', Basketball: '🏀', Badminton: '🏸' };
const initials = (n) => n.replace(/^NSBM\s+/i, '').split(/\s+/).map((w) => w[0]).slice(0, 2).join('').toUpperCase();
const pd = (s) => new Date(String(s).replace(' ', 'T'));
const fmtDay = (s) => pd(s).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
const fmtTime = (s) => pd(s).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
const hourLabel = (h) => `${((h + 11) % 12) + 1}:00 ${h < 12 ? 'AM' : 'PM'}`;
const hourShort = (h) => `${((h + 11) % 12) + 1} ${h < 12 ? 'AM' : 'PM'}`;
const ord = (n) => ['1st', '2nd', '3rd'][n - 1] || n + 'th';
const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
const today = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };
const needLogin = () => { if (S.user) return false; openAuth('login'); showToast('Please log in first'); return true; };

/* ---------------------------------------------------------------- routing */
function route() {
  let t = location.hash.slice(1); if (!tabNames.includes(t)) t = 'home';
  document.querySelectorAll('.view').forEach((v) => v.classList.toggle('hidden', v.id !== 'view-' + t));
  document.querySelectorAll('[data-tab]').forEach((a) => {
    const on = a.dataset.tab === t;
    a.classList.toggle('bg-card', on); a.classList.toggle('text-white', on); a.classList.toggle('text-slate-400', !on);
    on ? a.setAttribute('aria-current', 'page') : a.removeAttribute('aria-current');
  });
  if (t === 'home') countUp();
  window.scrollTo(0, 0);
}
addEventListener('hashchange', route);

/* ---------------------------------------------------------------- auth UI */
function renderAuth() {
  const u = S.user;
  $('authArea').innerHTML = u
    ? `<div class="flex items-center gap-2 pl-1 pr-3 py-1 rounded-full bg-card border border-line">
         <span class="w-8 h-8 rounded-full bg-em grid place-items-center text-white text-sm font-semibold">${esc(u.name[0].toUpperCase())}</span>
         <span class="hidden sm:block text-sm">${esc(u.name.split(' ')[0])}</span></div>
       ${u.role === 'admin' ? '<a href="admin/" class="btn hidden sm:block rounded-lg border border-mint text-mint hover:bg-mint/10 text-sm px-3 py-2">Admin</a>' : ''}
       <button id="logoutBtn" class="btn rounded-lg bg-card border border-line hover:border-mint text-sm px-3 py-2">Log out</button>`
    : `<button data-auth="login" class="btn rounded-lg bg-card border border-line hover:border-mint text-sm px-3 py-2">Log in</button>
       <button data-auth="register" class="btn rounded-lg bg-em hover:bg-emerald-700 text-white text-sm px-3 py-2">Sign up</button>`;
}
document.addEventListener('click', async (e) => {
  const a = e.target.closest('[data-auth]');
  if (a) { openAuth(a.dataset.auth); return; }
  if (e.target.closest('#logoutBtn')) { await api('logout', {}); showToast('Logged out'); load(); }
});

let authMode = 'login';
function openAuth(mode) {
  authMode = mode;
  $('authModal').classList.remove('hidden');
  $('authErr').textContent = '';
  $('authTitle').textContent = mode === 'login' ? 'Log in' : 'Create your account';
  document.querySelectorAll('[data-reg]').forEach((el) => el.classList.toggle('hidden', mode === 'login'));
  $('authSubmit').textContent = mode === 'login' ? 'Log in' : 'Sign up';
  $('authSwitch').innerHTML = mode === 'login'
    ? 'New here? <button type="button" class="text-mint hover:underline" data-switch="register">Create an account</button>'
    : 'Already registered? <button type="button" class="text-mint hover:underline" data-switch="login">Log in</button>';
  $('authDemo').classList.toggle('hidden', mode !== 'login');
  setTimeout(() => (mode === 'login' ? $('f_email') : $('f_name')).focus(), 30);
}
const closeAuth = () => $('authModal').classList.add('hidden');
$('authClose').onclick = closeAuth;
$('authModal').addEventListener('click', (e) => { if (e.target.id === 'authModal') closeAuth(); const s = e.target.closest('[data-switch]'); if (s) openAuth(s.dataset.switch); });
addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAuth(); });
$('authForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const body = { email: $('f_email').value, password: $('f_pass').value };
  if (authMode === 'register') { body.full_name = $('f_name').value; body.student_id = $('f_sid').value; }
  $('authSubmit').disabled = true;
  const r = await api(authMode, body);
  $('authSubmit').disabled = false;
  if (!r.ok) { $('authErr').textContent = r.error || 'Something went wrong.'; return; }
  closeAuth(); $('authForm').reset();
  await load();
  showToast(authMode === 'login' ? `Welcome back, ${S.user.name.split(' ')[0]}!` : 'Account created. Welcome to SportsVerse!');
});

/* ---------------------------------------------------------------- home */
function liveHTML(m) {
  return `<div class="flex items-center justify-between mb-4">
      <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-500/15 text-red-400 text-sm font-semibold"><span class="w-2 h-2 rounded-full bg-red-500 pulse"></span>LIVE</span>
      <span class="text-sm text-slate-400">${esc(m.sport)} · ${esc(m.venue)}</span></div>
    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 sm:gap-8">
      <div class="flex flex-col sm:flex-row items-center sm:justify-end gap-3 text-center sm:text-right">
        <span class="font-semibold text-lg sm:text-2xl text-white order-2 sm:order-1">${esc(m.home)}</span>
        <span class="order-1 sm:order-2 w-14 h-14 rounded-full ${m.ours ? 'bg-em' : 'bg-slate-600'} grid place-items-center font-bold text-white">${esc(initials(m.home))}</span></div>
      <div class="text-center"><div class="text-4xl sm:text-6xl font-bold text-white tabular-nums">${m.home_score ?? 0} <span class="text-slate-500">:</span> ${m.away_score ?? 0}</div>
        <div class="text-mint text-sm mt-1">Started ${esc(fmtTime(m.date))}</div></div>
      <div class="flex flex-col sm:flex-row items-center gap-3 text-center sm:text-left">
        <span class="w-14 h-14 rounded-full bg-slate-600 grid place-items-center font-bold text-white">${esc(initials(m.away))}</span>
        <span class="font-semibold text-lg sm:text-2xl text-white">${esc(m.away)}</span></div></div>`;
}
function renderLive() {
  const live = S.matches.find((m) => m.status === 'Live');
  document.querySelectorAll('[data-live]').forEach((el) => {
    el.classList.toggle('hidden', !live);
    if (live) el.innerHTML = liveHTML(live);
  });
}
function renderHero() {
  const rugby = S.clubs.find((c) => c.sport_name === 'Rugby') || S.clubs[0];
  if (!rugby) return;
  $('heroMembers').dataset.count = rugby.members; $('heroMembers').textContent = rugby.members;
  $('heroTrain').textContent = rugby.training_schedule ? 'Trains ' + rugby.training_schedule : 'Join the squad';
}
function renderPassport() {
  const u = S.user, p = S.passport;
  if (!u) {
    $('passport').innerHTML = `<h2 class="font-semibold text-white mb-4">My Sports Passport</h2>
      <p class="text-sm text-slate-400">Log in to see your points, level, clubs and facility bookings.</p>
      <div class="mt-5 flex gap-3"><button data-auth="login" class="btn rounded-xl bg-em hover:bg-emerald-700 text-white font-medium px-5 py-2.5 text-sm">Log in</button>
      <button data-auth="register" class="btn rounded-xl border border-mint text-mint hover:bg-mint/10 font-medium px-5 py-2.5 text-sm">Sign up</button></div>`;
    return;
  }
  const into = p.points % 250, pct = Math.round((into / 250) * 100);
  $('passport').innerHTML = `<h2 class="font-semibold text-white mb-4">My Sports Passport</h2>
    <div class="flex items-center gap-4">
      <div class="w-20 h-20 rounded-full bg-gradient-to-br from-em to-emerald-900 grid place-items-center ring-4 ring-ink text-3xl font-semibold text-white">${esc(u.name[0].toUpperCase())}</div>
      <div class="min-w-0"><div class="text-xl font-semibold text-white truncate">${esc(u.name)}</div>
        <div class="text-xs text-slate-400">${esc(u.student_id || '')}</div>
        <span class="inline-flex items-center gap-1 mt-1 px-2.5 py-0.5 rounded-full bg-amber-400/15 text-amber-300 text-xs font-medium">Level ${p.level}</span></div>
      <div class="ml-auto text-right"><div class="text-2xl font-bold text-mint tabular-nums">${p.points.toLocaleString()}</div><div class="text-xs text-slate-400">PTS</div></div></div>
    <div class="mt-5"><div class="flex justify-between text-xs text-slate-400 mb-1.5"><span>Progress to Level ${p.level + 1}</span><span>${p.points} / ${p.next}</span></div>
      <div class="h-2.5 rounded-full bg-ink overflow-hidden" role="progressbar" aria-valuenow="${pct}" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-em" style="width:${pct}%"></div></div></div>
    <div class="mt-5 grid grid-cols-3 gap-3">
      ${[['Clubs', p.clubs], ['Bookings', p.bookings], ['Pending', p.pending]].map(([l, v]) => `<div class="rounded-xl bg-ink border border-line p-3 text-center"><div class="text-xl font-semibold text-white tabular-nums">${v}</div><div class="text-xs mt-1 text-slate-400">${l}</div></div>`).join('')}
    </div>
    <p class="mt-4 text-xs text-slate-500">100 pts per club you are approved in · 50 pts per approved booking.</p>`;
}

/* ---------------------------------------------------------------- clubs */
let cf = 'All', cq = '';
function renderClubs() {
  const sports = [...new Set(S.clubs.map((c) => c.sport_name))];
  $('clubChips').innerHTML = ['All', 'Joined', ...sports].map((o) => chipBtn(o, o === cf)).join('');
  const list = S.clubs.filter((c) => (cf === 'All' || (cf === 'Joined' ? c.my_status === 'approved' || c.my_status === 'pending' : c.sport_name === cf)) && (c.name + ' ' + c.sport_name).toLowerCase().includes(cq));
  $('club-empty').classList.toggle('hidden', list.length > 0);
  $('club-grid').innerHTML = list.map((c) => {
    const left = c.max_members - c.members, full = left <= 0 && !c.my_status;
    const pct = c.max_members ? Math.min(100, (c.members / c.max_members) * 100) : 0;
    let label = 'Join club', cls = 'bg-em hover:bg-emerald-700 text-white';
    if (c.my_status === 'approved') { label = 'Leave club'; cls = 'border border-mint text-mint hover:bg-mint/10'; }
    else if (c.my_status === 'pending') { label = 'Request pending · Cancel'; cls = 'border border-amber-300/60 text-amber-300 hover:bg-amber-300/10'; }
    else if (full) { label = 'Club full'; cls = 'bg-slate-700 text-slate-400 cursor-not-allowed'; }
    return `<div class="card p-5 flex flex-col">
      <div class="flex items-start justify-between gap-3"><span class="w-12 h-12 rounded-xl grid place-items-center text-2xl ${sportCls[c.sport_name] || 'bg-slate-500/15 text-slate-300'}">${sportIcon[c.sport_name] || '🏅'}</span>
        <span class="text-xs px-2.5 py-1 rounded-full ${sportCls[c.sport_name] || 'bg-slate-500/15 text-slate-300'}">${esc(c.sport_name)}</span></div>
      <h3 class="mt-3 font-semibold text-white">${esc(c.name)}</h3>
      <p class="text-sm text-slate-400 mt-1">${esc(c.training_schedule || 'Schedule to be announced')}</p>
      <div class="mt-4"><div class="flex justify-between text-xs text-slate-400 mb-1.5"><span>${c.members} / ${c.max_members} members</span><span class="${left <= 5 ? 'text-amber-300' : ''}">${left <= 0 ? 'Full' : left <= 5 ? left + ' spots left' : ''}</span></div>
        <div class="h-1.5 rounded-full bg-ink overflow-hidden"><div class="h-full rounded-full bg-em" style="width:${pct}%"></div></div></div>
      <button data-club="${c.club_id}" ${full ? 'disabled' : ''} class="btn mt-4 rounded-xl py-2.5 font-medium ${cls}">${label}</button></div>`;
  }).join('');
}
$('club-grid').addEventListener('click', async (e) => {
  const b = e.target.closest('button[data-club]'); if (!b || b.disabled) return;
  if (needLogin()) return;
  const c = S.clubs.find((x) => x.club_id === +b.dataset.club);
  const r = await api(c.my_status ? 'leave_club' : 'join_club', { club_id: c.club_id });
  if (!r.ok) { showToast(r.error); return; }
  showToast(c.my_status ? `You left ${c.name}` : `Request sent to ${c.name}. An admin will approve it.`);
  await load();
});
bind('clubChips', (v) => { cf = v; renderClubs(); });
$('clubSearch').addEventListener('input', (e) => { cq = e.target.value.trim().toLowerCase(); renderClubs(); });
$('clubClear').onclick = () => { cf = 'All'; cq = ''; $('clubSearch').value = ''; renderClubs(); };

/* ---------------------------------------------------------------- matches */
let mTab = 'Upcoming', mSport = 'All';
const remind = new Set((() => { try { return JSON.parse(localStorage.getItem('sv_remind') || '[]'); } catch (e) { return []; } })());
const saveRemind = () => { try { localStorage.setItem('sv_remind', JSON.stringify([...remind])); } catch (e) {} };
const bell = '<svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a2 2 0 0 0 3.4 0"/></svg>';
function renderMatches() {
  const upcoming = S.matches.filter((m) => m.status === 'Scheduled');
  const results = S.matches.filter((m) => m.status === 'Completed').reverse();
  $('home-upcoming').innerHTML = upcoming.slice(0, 3).map((m) => `<div class="py-3"><div class="text-xs text-slate-400">${esc(fmtDay(m.date))} · ${esc(fmtTime(m.date))} · ${esc(m.sport)}</div><div class="text-white font-medium">${esc(m.home)} <span class="text-slate-500 font-normal">vs</span> ${esc(m.away)}</div></div>`).join('') || '<p class="py-3 text-sm text-slate-400">No upcoming matches.</p>';
  $('mSeg').innerHTML = ['Upcoming', 'Results'].map((o) => segBtn(o, o === mTab)).join('');
  const sports = [...new Set(S.matches.map((m) => m.sport))];
  $('mChips').innerHTML = ['All', ...sports].map((o) => chipBtn(o, o === mSport)).join('');
  const ok = (sp) => mSport === 'All' || sp === mSport;
  const items = mTab === 'Upcoming'
    ? upcoming.filter((m) => ok(m.sport)).map((m) => {
      const d = pd(m.date), on = remind.has(m.id);
      return `<div class="rounded-xl bg-ink border border-line p-4 flex items-center gap-4"><div class="w-14 shrink-0 text-center rounded-lg bg-card border border-line py-1.5"><div class="text-xs text-slate-400">${d.toLocaleDateString('en-GB', { weekday: 'short' })}</div><div class="text-lg font-semibold text-white leading-tight">${d.getDate()}</div><div class="text-xs text-slate-400">${d.toLocaleDateString('en-GB', { month: 'short' })}</div></div>
        <div class="min-w-0 flex-1"><div class="text-white font-medium truncate">${esc(m.home)} <span class="text-slate-500 font-normal">vs</span> ${esc(m.away)}</div><div class="text-xs text-slate-400 mt-0.5">${esc(fmtTime(m.date))} · ${esc(m.sport)} · ${esc(m.venue)}</div></div>
        <button data-r="${m.id}" aria-pressed="${on}" aria-label="${on ? 'Reminder on' : 'Remind me'}" class="btn shrink-0 w-10 h-10 rounded-lg border grid place-items-center ${on ? 'bg-em border-em text-white' : 'border-line text-slate-300 hover:border-mint'}">${bell}</button></div>`;
    })
    : results.filter((m) => ok(m.sport)).map((m) => {
      const ourId = m.ours ? (S.matches && S.clubs.find((c) => c.club_id === m.home_id) ? m.home_id : m.away_id) : null;
      const draw = m.winner_id === null, win = ourId && m.winner_id === ourId;
      const tag = !ourId ? ['FT', 'bg-slate-500/15 text-slate-300'] : draw ? ['Draw', 'bg-slate-500/15 text-slate-300'] : win ? ['Win', 'bg-emerald-400/15 text-emerald-300'] : ['Loss', 'bg-red-400/15 text-red-300'];
      return `<div class="rounded-xl bg-ink border border-line p-4 flex items-center gap-4"><span class="w-12 shrink-0 text-center text-xs font-semibold py-1 rounded-md ${tag[1]}">${tag[0]}</span>
        <div class="min-w-0 flex-1"><div class="text-white truncate">${esc(m.home)} <span class="text-slate-500">vs</span> ${esc(m.away)}</div><div class="text-xs text-slate-400 mt-0.5">${esc(fmtDay(m.date))} · ${esc(m.sport)}</div></div>
        <div class="tabular-nums font-semibold text-white">${m.home_score ?? '–'} – ${m.away_score ?? '–'}</div></div>`;
    });
  $('mList').innerHTML = items.length ? items.join('') : `<p class="md:col-span-2 text-center text-sm text-slate-400 py-8">No ${mTab.toLowerCase()} ${mSport === 'All' ? '' : mSport + ' '}matches yet.</p>`;
}
bind('mSeg', (v) => { mTab = v; renderMatches(); });
bind('mChips', (v) => { mSport = v; renderMatches(); });
$('mList').addEventListener('click', (e) => {
  const b = e.target.closest('button[data-r]'); if (!b) return; const id = +b.dataset.r;
  const m = S.matches.find((x) => x.id === id);
  remind.has(id) ? remind.delete(id) : remind.add(id); saveRemind();
  showToast(remind.has(id) ? `Reminder set for ${m.home} vs ${m.away}` : 'Reminder removed'); renderMatches();
});

/* ---------------------------------------------------------------- tournaments */
let tSel = null;
const roundRank = (r) => { r = r.toLowerCase(); if (/16/.test(r)) return 0; if (/quarter/.test(r)) return 1; if (/semi/.test(r)) return 2; if (/final/.test(r)) return 3; return 1.5; };
function renderTournaments() {
  const root = $('tournamentRoot');
  if (!S.tournaments.length) { root.innerHTML = '<div class="card p-10 text-center text-slate-400">No tournaments yet.</div>'; return; }
  const t = S.tournaments.find((x) => x.id === tSel) || S.tournaments[0]; tSel = t.id;
  const byId = Object.fromEntries(S.matches.map((m) => [m.id, m]));
  const rounds = {};
  t.fixtures.forEach((f) => { (rounds[f.round] = rounds[f.round] || []).push(f); });
  const names = Object.keys(rounds).sort((a, b) => roundRank(a) - roundRank(b) || pd(rounds[a][0].date) - pd(rounds[b][0].date));
  const tick = '<svg viewBox="0 0 24 24" class="w-3 h-3 text-mint shrink-0" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5L20 7"/></svg>';
  const row = (name, score, win, lose, ours) => `<div class="flex items-center justify-between gap-2 px-2.5 py-1.5 ${win ? 'text-white font-semibold' : lose ? 'text-slate-500' : 'text-slate-300'}"><span class="truncate flex items-center gap-1">${win ? tick : ''}${esc(name)}</span><span class="tabular-nums">${score ?? ''}</span></div>`;
  const cols = names.map((r) => `<div class="w-48 shrink-0"><div class="text-sm font-medium text-slate-300 mb-3">${esc(r)}${/semi|quarter|final/i.test(r) ? '' : ''}</div><div class="grid gap-3">${rounds[r].map((f) => {
    const m = byId[f.match_id]; if (!m) return '';
    const done = m.status === 'Completed', live = m.status === 'Live';
    return `<div class="rounded-lg bg-ink border ${m.ours ? 'border-em ring-1 ring-em' : 'border-line'} text-xs divide-y divide-line">
      ${row(m.home, m.home_score, done && m.winner_id === m.home_id, done && m.winner_id === m.away_id)}
      ${row(m.away, m.away_score, done && m.winner_id === m.away_id, done && m.winner_id === m.home_id)}
      <div class="px-2.5 py-1 text-[11px] ${live ? 'text-red-400' : 'text-slate-500'}">${live ? '● LIVE' : done ? 'Full time' : esc(fmtDay(m.date) + ' · ' + fmtTime(m.date))}</div></div>`;
  }).join('')}</div></div>`).join('');
  const played = t.fixtures.filter((f) => byId[f.match_id] && byId[f.match_id].status === 'Completed').length;
  const next = t.fixtures.map((f) => byId[f.match_id]).filter((m) => m && m.status === 'Scheduled' && m.ours)[0];
  const picker = S.tournaments.length > 1 ? `<div class="flex gap-2 overflow-x-auto pb-3 mb-3" id="tChips">${S.tournaments.map((x) => chipBtn(x.name, x.id === t.id)).join('')}</div>` : '';
  root.innerHTML = `${picker}<div class="grid gap-6 lg:grid-cols-3">
    <section class="card p-5 lg:col-span-2"><div class="flex items-center justify-between flex-wrap gap-2 mb-4"><h2 class="font-semibold text-white">Knockout bracket</h2>
      <span class="flex items-center gap-2 text-xs text-slate-400"><i class="w-3 h-3 rounded border border-em ring-1 ring-em inline-block"></i>NSBM team</span></div>
      <p class="sm:hidden text-xs text-slate-400 mb-2">Swipe sideways to see every round.</p>
      <div class="overflow-x-auto pb-2"><div class="flex gap-6 min-w-max">${cols || '<p class="text-sm text-slate-400">No fixtures yet.</p>'}</div></div></section>
    <div class="grid gap-6 content-start">
      <section class="card p-5"><h2 class="font-semibold text-white mb-3">NSBM's next match</h2>${next
    ? `<div class="rounded-xl bg-ink border border-em p-4"><div class="text-xs text-slate-400">${esc(fmtDay(next.date))} · ${esc(fmtTime(next.date))} · ${esc(next.venue)}</div><div class="mt-1 text-white font-semibold">${esc(next.home)} <span class="text-slate-500 font-normal">vs</span> ${esc(next.away)}</div></div>`
    : '<p class="text-sm text-slate-400">No NSBM fixture scheduled yet.</p>'}</section>
      <section class="card p-5"><h2 class="font-semibold text-white mb-3">Tournament details</h2><dl class="text-sm divide-y divide-line">
        ${[['Sport', t.sport], ['Status', t.status], ['Teams', t.teams], ['Matches played', `${played} of ${t.fixtures.length}`], ['Dates', `${fmtDay(t.start + ' 00:00:00')} – ${fmtDay(t.end + ' 00:00:00')}`]].map(([k, v]) => `<div class="flex justify-between py-2"><dt class="text-slate-400">${k}</dt><dd class="text-white text-right">${esc(v)}</dd></div>`).join('')}</dl></section></div></div>`;
  $('tournamentSub').textContent = `${t.name} · ${t.sport} · knockout`;
  const chips = $('tChips'); if (chips) chips.onclick = (e) => { const b = e.target.closest('button[data-v]'); if (b) { tSel = S.tournaments.find((x) => x.name === b.dataset.v).id; renderTournaments(); } };
}

/* ---------------------------------------------------------------- leaderboard */
let lbP = 'Points', lbS = 'All';
function renderLB() {
  $('lbSeg').innerHTML = ['Points', 'Wins'].map((o) => segBtn(o, o === lbP)).join('');
  const sports = [...new Set(S.leaderboard.map((t) => t.sport))];
  $('lbChips').innerHTML = ['All', ...sports].map((o) => chipBtn(o, o === lbS)).join('');
  const key = lbP === 'Points' ? 'points' : 'wins';
  const list = S.leaderboard.filter((t) => lbS === 'All' || t.sport === lbS).sort((a, b) => b[key] - a[key] || b.wins - a.wins || a.played - b.played || a.name.localeCompare(b.name));
  const mineIds = new Set(S.clubs.filter((c) => c.my_status === 'approved').map((c) => c.club_id));
  const crown = '<svg viewBox="0 0 24 24" class="w-6 h-6 mx-auto text-amber-300 mb-1" fill="currentColor"><path d="M3 7l4 4 5-7 5 7 4-4-2 12H5z"/></svg>';
  $('podium').innerHTML = list.length ? [2, 1, 3].map((r) => {
    const a = list[r - 1]; if (!a) return '<div></div>';
    const me = mineIds.has(a.club_id), h = { 1: 'h-32', 2: 'h-24', 3: 'h-16' }[r];
    return `<div class="text-center">${r === 1 ? crown : ''}<span class="mx-auto rounded-full grid place-items-center font-semibold text-white ${r === 1 ? 'w-14 h-14 ring-2 ring-amber-300' : 'w-12 h-12'} ${me || !a.external ? 'bg-em' : 'bg-slate-600'}">${esc(initials(a.name))}</span>
      <div class="mt-2 text-sm font-medium text-white truncate">${esc(a.name)}</div><div class="text-xs mb-2 tabular-nums ${r === 1 ? 'text-mint' : 'text-slate-400'}">${a[key]} ${key}</div>
      <div class="${h} rounded-t-xl grid place-items-center text-3xl font-bold ${r === 1 ? 'bg-em/25 border border-em text-mint' : 'bg-ink border border-line text-slate-300'}">${r}</div></div>`;
  }).join('') : '<p class="col-span-3 text-center text-sm text-slate-400 py-6">No completed matches yet.</p>';
  const mi = list.findIndex((t) => mineIds.has(t.club_id));
  $('lbYou').innerHTML = !S.user
    ? '<p class="text-sm text-slate-400">Log in and join a club to see where your team stands.</p>'
    : mi < 0 ? '<p class="text-sm text-slate-400">Join a club (and get approved) to track your team here.</p>'
      : `<div class="flex items-center justify-between"><div><div class="text-xs text-slate-400">Your club</div><div class="text-white font-semibold">${esc(list[mi].name)}</div></div><div class="text-right"><div class="text-2xl font-bold text-white">${ord(mi + 1)}</div><div class="text-xs text-slate-400">${list[mi][key]} ${key}</div></div></div>
         <p class="mt-2 text-sm text-slate-400">${mi === 0 ? 'Your club is leading the table.' : `${list[mi - 1][key] - list[mi][key]} ${key} behind ${esc(list[mi - 1].name)}.`}</p>`;
  const top = list.length ? Math.max(1, list[0][key]) : 1;
  $('rank-list').innerHTML = list.map((a, i) => {
    const me = mineIds.has(a.club_id);
    return `<li class="py-2.5 flex items-center gap-3 ${me ? 'bg-em/10 -mx-2 px-2 rounded-lg' : ''}"><span class="w-6 text-slate-400 tabular-nums">${i + 1}</span>
      <span class="w-8 h-8 rounded-full grid place-items-center text-xs ${!a.external ? 'bg-em text-white' : 'bg-slate-600 text-white'}">${esc(initials(a.name))}</span>
      <div class="flex-1 min-w-0"><div class="${me ? 'text-mint font-semibold' : 'text-slate-200'} truncate">${esc(a.name)}</div><div class="text-[11px] text-slate-500">${a.wins}W ${a.draws}D ${a.losses}L · ${esc(a.sport)}</div>
        <div class="h-1 mt-1 rounded-full bg-ink overflow-hidden"><div class="h-full bg-em/70" style="width:${Math.round((a[key] / top) * 100)}%"></div></div></div>
      <span class="w-14 text-right tabular-nums text-slate-200">${a[key]}</span></li>`;
  }).join('');
}
bind('lbSeg', (v) => { lbP = v; renderLB(); });
bind('lbChips', (v) => { lbS = v; renderLB(); });

/* ---------------------------------------------------------------- facilities + booking */
const dot = { Open: 'bg-emerald-400', 'In use': 'bg-amber-400', Maintenance: 'bg-red-400' };
let selHour = null, slotData = [], slotStatus = 'Open';
function renderFacilities() {
  $('fac-list').innerHTML = S.facilities.map((f) => `<div class="py-3 flex items-center justify-between gap-3"><div><div class="text-white font-medium">${esc(f.facility_name)}</div><div class="text-xs text-slate-400">${esc(f.location || '')} · ${hourShort(f.open_hour)} – ${hourShort(f.close_hour)}</div></div><span class="flex items-center gap-2 text-sm text-slate-300"><i class="w-2 h-2 rounded-full ${dot[f.status] || 'bg-slate-400'}"></i>${esc(f.status)}</span></div>`).join('');
  const sel = $('court'), cur = sel.value;
  sel.innerHTML = S.facilities.map((f) => `<option value="${f.facility_id}" ${f.status === 'Maintenance' ? 'disabled' : ''}>${esc(f.facility_name)}${f.status === 'Maintenance' ? ' (maintenance)' : ''}</option>`).join('');
  if (cur && [...sel.options].some((o) => o.value === cur && !o.disabled)) sel.value = cur;
  else { const first = [...sel.options].find((o) => !o.disabled); if (first) sel.value = first.value; }
  renderMyBookings();
}
const bBadge = { pending: 'bg-amber-400/15 text-amber-300', approved: 'bg-emerald-400/15 text-emerald-300', rejected: 'bg-red-400/15 text-red-300' };
function renderMyBookings() {
  const box = $('myBookings');
  if (!S.user) { box.innerHTML = '<p class="text-sm text-slate-400">Log in to request a booking and see your requests here.</p>'; return; }
  box.innerHTML = S.bookings.length ? S.bookings.map((b) => `<div class="py-2.5 flex items-center gap-3"><div class="flex-1 min-w-0"><div class="text-sm text-white truncate">${esc(b.facility_name)}</div><div class="text-xs text-slate-400">${esc(fmtDay(b.booking_date + ' 00:00:00'))} · ${hourLabel(+b.slot_hour)}</div></div>
    <span class="text-xs px-2 py-0.5 rounded-full ${bBadge[b.status] || ''}">${esc(b.status)}</span>
    ${b.status === 'pending' ? `<button data-cancel="${b.booking_id}" class="btn text-xs text-slate-400 hover:text-red-300 underline">Cancel</button>` : ''}</div>`).join('') : '<p class="text-sm text-slate-400">No bookings yet.</p>';
}
async function loadSlots() {
  const fid = $('court').value, date = $('bdate').value;
  if (!fid || !date) return;
  const r = await fetch(`api.php?action=slots&facility_id=${fid}&date=${date}`, { credentials: 'same-origin' }).then((x) => x.json());
  slotData = r.ok ? r.slots : []; slotStatus = r.status || 'Open';
  if (!slotData.some((s) => s.hour === selHour && !s.booked)) selHour = null;
  renderSlots();
}
function renderSlots() {
  const isToday = $('bdate').value === today(), nowH = new Date().getHours();
  $('slots').innerHTML = slotData.map((s) => {
    const past = isToday && s.hour <= nowH, off = s.booked || past;
    const cls = off ? 'bg-slate-700 text-slate-400 cursor-not-allowed ' + (s.booked ? 'line-through' : 'opacity-60') : s.hour === selHour ? 'bg-em text-white border-em' : 'bg-ink border-line hover:border-mint';
    return `<button class="slot rounded-lg border py-2 ${cls}" ${off ? 'disabled' : ''} aria-pressed="${s.hour === selHour}" data-h="${s.hour}">${hourLabel(s.hour)}</button>`;
  }).join('') || '<p class="col-span-3 text-sm text-slate-400">No slots available.</p>';
  $('confirm').disabled = selHour === null;
  $('confirm').textContent = selHour !== null ? `Request ${hourLabel(selHour)}` : 'Select a time to continue';
}
$('slots').addEventListener('click', (e) => { const b = e.target.closest('button[data-h]'); if (!b) return; const h = +b.dataset.h; selHour = selHour === h ? null : h; $('toast').classList.add('hidden'); renderSlots(); });
$('court').addEventListener('change', () => { selHour = null; loadSlots(); });
$('bdate').addEventListener('change', () => { selHour = null; loadSlots(); });
$('confirm').addEventListener('click', async () => {
  if (needLogin()) return;
  const r = await api('book', { facility_id: +$('court').value, date: $('bdate').value, hour: selHour });
  const t = $('toast'); t.classList.remove('hidden');
  if (!r.ok) { t.textContent = r.error; t.className = 'mt-3 text-sm text-red-300'; await loadSlots(); return; }
  t.className = 'mt-3 text-sm text-mint';
  t.textContent = `Request sent: ${$('court').selectedOptions[0].text}, ${hourLabel(selHour)}. An admin will approve it.`;
  selHour = null;
  await load();
});
$('myBookings').addEventListener('click', async (e) => {
  const b = e.target.closest('button[data-cancel]'); if (!b) return;
  await api('cancel_booking', { booking_id: +b.dataset.cancel }); showToast('Booking request cancelled'); await load();
});

/* ---------------------------------------------------------------- hero effects */
function countUp() {
  document.querySelectorAll('[data-count]').forEach((el) => {
    const to = +el.dataset.count || 0;
    if (reduceMotion) { el.textContent = to; return; }
    const t0 = performance.now() + 1300;
    (function f(now) { const k = Math.max(0, Math.min(1, (now - t0) / 1200)); el.textContent = Math.round(to * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(f); })(performance.now());
  });
}
if (!reduceMotion) {
  const hero = $('hero'), par = $('heroParallax');
  let px = 0, py = 0, tx = 0, ty = 0;
  hero.addEventListener('pointermove', (e) => { const r = hero.getBoundingClientRect(); tx = ((e.clientX - r.left) / r.width - .5) * -26; ty = ((e.clientY - r.top) / r.height - .5) * -14; });
  hero.addEventListener('pointerleave', () => { tx = 0; ty = 0; });
  (function loop() {
    if (hero.offsetParent) { px += (tx - px) * .08; py += (ty - py) * .08; par.style.transform = `translate3d(${px}px,${py + Math.min(scrollY, 500) * .12}px,0)`; }
    requestAnimationFrame(loop);
  })();
}

/* ---------------------------------------------------------------- boot */
let first = true;
function renderAll() {
  renderAuth(); renderHero(); renderLive(); renderPassport(); renderClubs(); renderMatches();
  renderTournaments(); renderLB(); renderFacilities();
  if (first) { first = false; $('bdate').min = today(); $('bdate').value = today(); route(); }
  loadSlots();
}
$('bdate').max = (() => { const d = new Date(); d.setDate(d.getDate() + 30); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; })();
load();
setInterval(() => { if (!document.hidden && S) load(); }, 60000); // keep live scores fresh
