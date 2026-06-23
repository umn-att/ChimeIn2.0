function onOpen() {
  SlidesApp.getUi()
    .createMenu('ChimeIn')
    .addItem('Open ChimeIn Sidebar', 'showSidebar')
    .addItem('Clear Saved Token', 'clearAuthToken')
    .addToUi();
}

function showSidebar() {
  var template = HtmlService.createTemplateFromFile('Sidebar');
  template.userEmail = Session.getActiveUser().getEmail() || '';
  template.baseUrl = getBaseUrl();

  var html = template
    .evaluate()
    .setTitle('ChimeIn for Slides');

  SlidesApp.getUi().showSidebar(html);
}

function include(filename) {
  return HtmlService.createHtmlOutputFromFile(filename).getContent();
}
