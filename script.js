jQuery(document).ready(function() {
  var $headers = jQuery('.eft-header');
  // Convert EFT headers to Osmium links
  $headers.each(function(index, element) {
    var $element = jQuery(element),
        dna = $element.data('dna'),
        url = "https://o.smium.org/loadout/dna/" + dna + '?mangle=0',
        $newHeader = jQuery("<a href='#'></a>");
    // Skip empty DNA strings
    if(dna === "") {
      return true;
    }
    if(typeof CCPEVE !== "undefined") {
      // It doesn't seem like the IGB actually executes this JS file. If it
      // does, this /should/ work, but it's a pretty narrow use case.
      $newHeader.on('click', function(ev) {
        CCPEVE.showFitting(dna);
        return false;
      });
    } else {
      $newHeader.attr('href', url);
    }
    $newHeader.text($element.text());
    $element.replaceWith($newHeader);
  });
});
// vim:ts=2:sw=2:et:
