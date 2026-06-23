function insertSessionResults(chimeId, sessionId) {
  if (!chimeId || !sessionId) {
    throw new Error('Both chime ID and session ID are required.');
  }

  var data = fetchSessionResults(chimeId, sessionId);
  var page = SlidesApp.getActivePresentation().getSelection().getCurrentPage();
  if (!page) {
    throw new Error('Select a slide before inserting results.');
  }

  var title = data.question && data.question.text ? data.question.text : 'ChimeIn Results';
  var total = data.total_responses || 0;

  var lines = [
    title,
    '',
    'Total Responses: ' + total,
    ''
  ];

  if (data.results && data.results.type === 'multiple_choice' && Array.isArray(data.results.choices)) {
    data.results.choices.forEach(function (choice) {
      lines.push('- ' + choice.choice + ': ' + choice.count + ' (' + choice.percentage + '%)');
    });
  } else if (data.results && data.results.type === 'slider') {
    lines.push('Min: ' + data.results.min);
    lines.push('Avg: ' + data.results.average);
    lines.push('Median: ' + data.results.median);
    lines.push('Max: ' + data.results.max);
  } else {
    lines.push('Result type: ' + (data.results ? data.results.type : 'unknown'));
    lines.push('Use custom renderer in next phase for rich visualization.');
  }

  var box = page.insertShape(SlidesApp.ShapeType.TEXT_BOX, 320, 120, 380, 300);
  box.getText().setText(lines.join('\n'));

  return { inserted: true };
}
