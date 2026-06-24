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

  // size is a pixel value for API resolution; divide by 2 to get reasonable points on slide
  var slideSize = Math.round(size / 2);
  var placement = getQrPlacement(layout, slideSize, includeJoinText);
  var image = page.insertImage(qrBlob);
  image.setWidth(slideSize).setHeight(slideSize).setLeft(placement.left).setTop(placement.top);

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

function getQrPlacement(layout, slideSize, includeJoinText) {
  // Standard 16:9 Google Slides dimensions in points
  var SLIDE_WIDTH = 720;
  var SLIDE_HEIGHT = 405;
  var TEXT_HEIGHT = 36;
  var GAP = 10;
  var MARGIN = 40;

  // Vertically center the QR + optional text as a group
  var totalHeight = includeJoinText ? slideSize + GAP + TEXT_HEIGHT : slideSize;
  var top = Math.max(MARGIN, Math.round((SLIDE_HEIGHT - totalHeight) / 2));
  var textTop = top + slideSize + GAP;

  switch (layout) {
    case 'center': {
      var left = Math.round((SLIDE_WIDTH - slideSize) / 2);
      var textWidth = Math.max(slideSize + 60, 280);
      var textLeft = Math.round((SLIDE_WIDTH - textWidth) / 2);
      return { left: left, top: top, textLeft: textLeft, textTop: textTop, textWidth: textWidth, textHeight: TEXT_HEIGHT };
    }
    case 'right': {
      var left = SLIDE_WIDTH - MARGIN - slideSize;
      var textWidth = Math.max(slideSize + 40, 260);
      var textLeft = SLIDE_WIDTH - MARGIN - textWidth;
      return { left: left, top: top, textLeft: textLeft, textTop: textTop, textWidth: textWidth, textHeight: TEXT_HEIGHT };
    }
    case 'left':
    default: {
      var textWidth = Math.max(slideSize + 40, 260);
      return { left: MARGIN, top: top, textLeft: MARGIN, textTop: textTop, textWidth: textWidth, textHeight: TEXT_HEIGHT };
    }
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
