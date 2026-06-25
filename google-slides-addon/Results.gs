/**
 * Open a live-refreshing modeless dialog showing poll results.
 * Called from the sidebar via google.script.run.
 */
function showLiveResultsDialog(chimeId, sessionId) {
  if (!chimeId || !sessionId) {
    throw new Error('Chime ID and Session ID are required.');
  }

  var template = HtmlService.createTemplateFromFile('ResultsDialog');
  template.chimeId = String(chimeId);
  template.sessionId = String(sessionId);

  var html = template.evaluate()
    .setWidth(480)
    .setHeight(400)
    .setSandboxMode(HtmlService.SandboxMode.IFRAME);

  SlidesApp.getUi().showModelessDialog(html, 'Live Results');
}

/**
 * Fetch results for the dialog's polling loop.
 * Returns the same shape as fetchSessionResults().
 */
function fetchResultsForDialog(chimeId, sessionId) {
  return fetchSessionResults(chimeId, sessionId);
}

function insertSessionResults(chimeId, sessionId, options) {
  if (!chimeId || !sessionId) {
    throw new Error('Both chime ID and session ID are required.');
  }

  var opts = options || {};
  var displayMode = opts.displayMode || 'table'; // table | text | both
  var layout = opts.layout || 'right'; // right | full

  var data = fetchSessionResults(chimeId, sessionId);
  var page = SlidesApp.getActivePresentation().getSelection().getCurrentPage();
  if (!page) {
    throw new Error('Select a slide before inserting results.');
  }

  clearManagedResultsElements(page);

  var title = data.question && data.question.text ? data.question.text : 'ChimeIn Results';
  var total = data.total_responses || 0;
  var updatedAt = data.updated_at || new Date().toISOString();

  var frame = getResultsFrame(layout);
  var currentTop = frame.top;

  var titleShape = page.insertShape(
    SlidesApp.ShapeType.TEXT_BOX,
    frame.left,
    currentTop,
    frame.width,
    50
  );
  titleShape.setTitle('ChimeInResultsTitle');
  titleShape.getText().setText(title + '\nTotal Responses: ' + total + '  |  Updated: ' + updatedAt);
  currentTop += 60;

  if (isMultipleChoiceResults(data) && (displayMode === 'table' || displayMode === 'both')) {
    currentTop = renderMultipleChoiceTable(page, data, frame, currentTop);
  }

  if (displayMode === 'text' || displayMode === 'both' || !isMultipleChoiceResults(data)) {
    renderResultsSummaryText(page, data, frame, currentTop);
  }

  return {
    inserted: true,
    displayMode: displayMode,
    layout: layout
  };
}

function isMultipleChoiceResults(data) {
  return !!(
    data &&
    data.results &&
    data.results.type === 'multiple_choice' &&
    Array.isArray(data.results.choices)
  );
}

function clearManagedResultsElements(page) {
  var elements = page.getPageElements();
  elements.forEach(function (element) {
    if (String(element.getTitle() || '').indexOf('ChimeInResults') === 0) {
      element.remove();
    }
  });
}

function getResultsFrame(layout) {
  if (layout === 'full') {
    return {
      left: 40,
      top: 70,
      width: 620,
      height: 380
    };
  }

  return {
    left: 320,
    top: 110,
    width: 360,
    height: 320
  };
}

function renderMultipleChoiceTable(page, data, frame, top) {
  var choices = data.results.choices;
  var table = page.insertTable(choices.length + 1, 4, frame.left, top, frame.width, 28 + (choices.length * 26));
  table.setTitle('ChimeInResultsTable');

  table.getCell(0, 0).getText().setText('Choice');
  table.getCell(0, 1).getText().setText('Count');
  table.getCell(0, 2).getText().setText('Percent');
  table.getCell(0, 3).getText().setText('Correct');

  choices.forEach(function (choice, index) {
    var row = index + 1;
    table.getCell(row, 0).getText().setText(String(choice.choice || ''));
    table.getCell(row, 1).getText().setText(String(choice.count || 0));
    table.getCell(row, 2).getText().setText(String(choice.percentage || 0) + '%');
    table.getCell(row, 3).getText().setText(choice.correct ? 'Yes' : '');
  });

  return top + (32 + (choices.length * 26));
}

function renderResultsSummaryText(page, data, frame, top) {
  var lines = [];

  if (data.results && data.results.type === 'multiple_choice' && Array.isArray(data.results.choices)) {
    lines.push('Multiple Choice Summary');
    data.results.choices.forEach(function (choice) {
      lines.push('- ' + choice.choice + ': ' + choice.count + ' (' + choice.percentage + '%)');
    });
  } else if (data.results && data.results.type === 'slider') {
    lines.push('Slider Summary');
    lines.push('Min: ' + data.results.min);
    lines.push('Avg: ' + data.results.average);
    lines.push('Median: ' + data.results.median);
    lines.push('Max: ' + data.results.max);
  } else if (data.results && data.results.type === 'free_response') {
    lines.push('Free Responses');
    var responses = Array.isArray(data.results.responses) ? data.results.responses.slice(0, 8) : [];
    responses.forEach(function (response) {
      lines.push('- ' + (response.text || ''));
    });
    if (Array.isArray(data.results.responses) && data.results.responses.length > responses.length) {
      lines.push('...and ' + (data.results.responses.length - responses.length) + ' more');
    }
  } else if (data.results && data.results.type === 'image_response') {
    lines.push('Image Responses: ' + (data.results.total_responses || 0));
  } else if (data.results && data.results.type === 'heatmap') {
    lines.push('Heatmap Points: ' + (data.results.total_responses || 0));
    lines.push('Clusters: ' + ((data.results.clusters && data.results.clusters.length) || 0));
  } else if (data.results && data.results.type === 'text_heatmap') {
    lines.push('Text Highlight Responses: ' + (data.results.total_responses || 0));
  } else {
    lines.push('Result type: ' + (data.results ? data.results.type : 'unknown'));
    lines.push('No specialized renderer available.');
  }

  var box = page.insertShape(
    SlidesApp.ShapeType.TEXT_BOX,
    frame.left,
    top,
    frame.width,
    frame.height - (top - frame.top)
  );
  box.setTitle('ChimeInResultsSummary');
  box.getText().setText(lines.join('\n'));
}
