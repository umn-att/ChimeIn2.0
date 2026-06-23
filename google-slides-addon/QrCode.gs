function insertQrCodeForChime(chimeId, options) {
  if (!chimeId) {
    throw new Error('Chime ID is required.');
  }

  var opts = options || {};
  var qrBlob = fetchQrCodeBlob(chimeId, opts.size || 300, opts.format || 'png');

  var presentation = SlidesApp.getActivePresentation();
  var page = presentation.getSelection().getCurrentPage();
  if (!page) {
    throw new Error('Select a slide before inserting a QR code.');
  }

  var image = page.insertImage(qrBlob);
  image.setWidth(200).setHeight(200).setLeft(40).setTop(120);

  if (opts.includeJoinText !== false) {
    var chime = fetchChime(chimeId);
    var joinCode = String(chime.access_code || '');
    var hyphenCode = joinCode.replace(/(\d{3})(\d{3})/, '$1-$2');
    var joinLine = 'Scan QR or go to chimein.umn.edu and enter ' + hyphenCode;

    var shape = page.insertShape(SlidesApp.ShapeType.TEXT_BOX, 40, 330, 460, 40);
    shape.getText().setText(joinLine);
  }

  return { inserted: true };
}
