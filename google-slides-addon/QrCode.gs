function insertQrCodeForChime(chimeId, options) {
  if (!chimeId) {
    throw new Error('Chime ID is required.');
  }

  var opts = options || {};
  var size = Number(opts.size || 300);
  var format = opts.format || 'png';
  var layout = opts.layout || 'left';
  var includeJoinText = opts.includeJoinText !== false;
  var allowFallback = opts.allowFallback !== false;
  var chime = fetchChime(chimeId);
  var joinUrl = getBaseUrl() + '/join/' + String(chime.access_code || '');

  var qrBlob;
  var fallbackUsed = false;
  try {
    qrBlob = fetchQrCodeBlob(chimeId, size, format);
  } catch (e) {
    if (!allowFallback) {
      throw e;
    }
    qrBlob = fetchFallbackQrCodeBlob(joinUrl, size, format);
    fallbackUsed = true;
  }

  var presentation = SlidesApp.getActivePresentation();
  var page = presentation.getSelection().getCurrentPage();
  if (!page) {
    throw new Error('Select a slide before inserting a QR code.');
  }

  var placement = getQrPlacement(layout);
  var image = page.insertImage(qrBlob);
  image.setWidth(placement.width).setHeight(placement.height).setLeft(placement.left).setTop(placement.top);

  if (includeJoinText) {
    var joinCode = String(chime.access_code || '');
    var hyphenCode = joinCode.replace(/(\d{3})(\d{3})/, '$1-$2');
    var joinHost = getBaseUrl().replace(/^https?:\/\//, '');
    var joinLine = 'Scan QR or go to ' + joinHost + ' and enter ' + hyphenCode;

    var shape = page.insertShape(
      SlidesApp.ShapeType.TEXT_BOX,
      placement.textLeft,
      placement.textTop,
      placement.textWidth,
      placement.textHeight
    );
    shape.getText().setText(joinLine);
  }

  return {
    inserted: true,
    fallbackUsed: fallbackUsed
  };
}

function getQrPlacement(layout) {
  switch (layout) {
    case 'center':
      return {
        left: 190,
        top: 120,
        width: 240,
        height: 240,
        textLeft: 120,
        textTop: 370,
        textWidth: 380,
        textHeight: 40
      };
    case 'right':
      return {
        left: 420,
        top: 120,
        width: 220,
        height: 220,
        textLeft: 310,
        textTop: 350,
        textWidth: 340,
        textHeight: 50
      };
    case 'left':
    default:
      return {
        left: 40,
        top: 120,
        width: 220,
        height: 220,
        textLeft: 40,
        textTop: 350,
        textWidth: 460,
        textHeight: 50
      };
  }
}

function fetchFallbackQrCodeBlob(joinUrl, size, format) {
  if (format !== 'png') {
    throw new Error('Fallback QR provider only supports PNG. Try PNG format or disable fallback.');
  }

  var clampedSize = Math.max(100, Math.min(500, Number(size || 300)));
  var fallbackUrl =
    'https://api.qrserver.com/v1/create-qr-code/?size=' +
    encodeURIComponent(clampedSize + 'x' + clampedSize) +
    '&data=' +
    encodeURIComponent(joinUrl);

  var response = UrlFetchApp.fetch(fallbackUrl, {
    method: 'get',
    muteHttpExceptions: true
  });

  var status = response.getResponseCode();
  if (status >= 400) {
    throw new Error('Fallback QR generation failed (' + status + ').');
  }

  return response.getBlob();
}
