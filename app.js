const students = [
  { id: '2601001', name: '青木 美咲', attendance: 94, attitude: 9, assignment: 8 },
  { id: '2601002', name: '伊藤 健太', attendance: 88, attitude: 8, assignment: 9 },
  { id: '2601003', name: '上田 彩', attendance: '', attitude: '', assignment: '' },
  { id: '2601004', name: '大野 陸', attendance: 72, attitude: 6, assignment: 7 },
  { id: '2601005', name: '加藤 結衣', attendance: 100, attitude: 10, assignment: 10 },
  { id: '2601006', name: '木村 翔', attendance: 58, attitude: 5, assignment: '' },
];

let weights = JSON.parse(localStorage.getItem('sansun-weights') || '{"attendance":50,"attitude":30,"assignment":20}');
let data = JSON.parse(localStorage.getItem('sansun-grades') || 'null') || students;
let dirty = false;
let staffMode = false;
let finalized = false;

const $ = (selector) => document.querySelector(selector);
const gradeRows = $('#grade-rows');

function calculation(student) {
  const values = [student.attendance, student.attitude, student.assignment];
  if (values.some(value => value === '' || value === null || value === undefined)) return null;
  const score = Math.round(student.attendance * weights.attendance / 100 + student.attitude * 10 * weights.attitude / 100 + student.assignment * 10 * weights.assignment / 100);
  const label = score >= 90 ? '秀' : score >= 80 ? '優' : score >= 70 ? '良' : score >= 60 ? '可' : '不可';
  const className = score >= 90 ? 's' : score >= 80 ? 'a' : score >= 70 ? 'b' : score >= 60 ? 'c' : 'd';
  return { score, label, className };
}

function valid(value, type) {
  if (value === '') return true;
  const num = Number(value);
  return type === 'attendance' ? num >= 0 && num <= 100 : num >= 1 && num <= 10;
}

function renderRows() {
  gradeRows.innerHTML = data.map((student, index) => {
    const result = calculation(student);
    const fields = [['attendance', '出席率'], ['attitude', '授業態度'], ['assignment', '提出課題']];
    const inputs = fields.map(([field, title]) => `<td><input aria-label="${student.name} ${title}" data-index="${index}" data-field="${field}" type="number" value="${student[field]}" ${staffMode || finalized ? 'disabled' : ''} class="${valid(student[field], field) ? '' : 'invalid'}" /></td>`).join('');
    return `<tr><td>${student.id}</td><td><strong>${student.name}</strong></td>${inputs}<td class="score">${result ? result.score : '—'}</td><td class="grade ${result?.className || ''}">${result ? result.label : '—'}</td><td><span class="status ${result ? 'done' : ''}">${result ? '入力済み' : '未入力'}</span></td></tr>`;
  }).join('');
  gradeRows.querySelectorAll('input').forEach(input => input.addEventListener('input', handleInput));
  updateProgress();
}

function handleInput(event) {
  const input = event.target;
  const { index, field } = input.dataset;
  data[index][field] = input.value === '' ? '' : Number(input.value);
  input.classList.toggle('invalid', !valid(input.value, field));
  dirty = true;
  updateSaveState();
  renderRows();
}

function updateProgress() {
  const complete = data.filter(calculation).length;
  $('#progress-number').textContent = `${complete} / ${data.length}`;
  $('#progress-bar').style.width = `${complete / data.length * 100}%`;
}

function updateSaveState() {
  $('#save-state').textContent = dirty ? '● 未保存の変更があります' : '未保存の変更はありません';
  $('#save-state').classList.toggle('dirty', dirty);
}

function updateWeightUI() {
  $('#attendance-weight').value = weights.attendance;
  $('#attitude-weight').value = weights.attitude;
  $('#assignment-weight').value = weights.assignment;
  $('#weight-summary').textContent = `出席率 ${weights.attendance}%　授業態度 ${weights.attitude}%　提出課題 ${weights.assignment}%`;
}

function toast(message) {
  const element = $('#toast'); element.textContent = message; element.classList.add('show');
  window.setTimeout(() => element.classList.remove('show'), 2600);
}

document.querySelectorAll('.nav-item').forEach(button => button.addEventListener('click', () => {
  document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
  document.querySelectorAll('.view').forEach(view => view.classList.remove('active'));
  button.classList.add('active');
  $(`#${button.dataset.view}-view`).classList.add('active');
  $('#page-title').textContent = button.textContent.trim();
}));

$('#save-btn').addEventListener('click', () => {
  if (gradeRows.querySelector('.invalid')) return toast('入力値の範囲を確認してください。');
  localStorage.setItem('sansun-grades', JSON.stringify(data)); dirty = false; updateSaveState(); toast('下書きを保存しました。');
});

$('#edit-weights').addEventListener('click', () => document.querySelector('[data-view="settings"]').click());

['attendance-weight', 'attitude-weight', 'assignment-weight'].forEach(id => $("#" + id).addEventListener('input', () => {
  const sum = ['attendance-weight', 'attitude-weight', 'assignment-weight'].reduce((total, key) => total + Number($('#' + key).value || 0), 0);
  $('#weight-total').textContent = `合計 ${sum}%`;
  $('#weight-total').classList.toggle('error', sum !== 100);
}));

$('#weight-save').addEventListener('click', () => {
  if (staffMode || finalized) return toast('この操作を行う権限がありません。');
  const next = { attendance: Number($('#attendance-weight').value), attitude: Number($('#attitude-weight').value), assignment: Number($('#assignment-weight').value) };
  const sum = Object.values(next).reduce((a, b) => a + b, 0);
  if (sum !== 100 || Object.values(next).some(value => value < 0 || value > 100)) return toast('評価重みの合計を100%にしてください。');
  weights = next; localStorage.setItem('sansun-weights', JSON.stringify(weights)); updateWeightUI(); renderRows(); dirty = true; updateSaveState(); toast('評価重みを保存しました。成績を再計算しました。');
});

$('#role-toggle').addEventListener('click', () => {
  staffMode = !staffMode;
  $('#role-toggle').textContent = staffMode ? '講師ビューに戻る' : '専任職員ビュー';
  $('#finalize-btn').hidden = !staffMode || finalized;
  document.querySelector('[data-view="settings"]').hidden = staffMode;
  if (staffMode && $('#settings-view').classList.contains('active')) document.querySelector('[data-view="grades"]').click();
  $('.sidebar-foot span:nth-child(2)').innerHTML = staffMode ? '佐藤 花子<br /><small>専任職員</small>' : '山田 太郎<br /><small>講師</small>';
  $('#save-btn').style.display = staffMode ? 'none' : '';
  renderRows(); toast(staffMode ? '専任職員ビューに切り替えました。' : '講師ビューに切り替えました。');
});

$('#sign-out').addEventListener('click', () => toast('ログアウト機能は本番実装で接続します。'));
$('#finalize-btn').addEventListener('click', () => {
  const incomplete = data.filter(student => !calculation(student));
  if (incomplete.length) return toast(`${incomplete.length}名の未入力があります。すべて入力後に確定できます。`);
  $('#confirm-dialog').showModal();
});
$('#cancel-confirm').addEventListener('click', () => $('#confirm-dialog').close());
$('#confirm-grades').addEventListener('click', () => {
  finalized = true; $('#confirm-dialog').close(); $('#finalize-btn').hidden = true; renderRows();
  toast('2026年度 前期の成績を確定しました。');
});
renderRows(); updateWeightUI(); updateSaveState();
