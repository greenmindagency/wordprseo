<?php wp_footer(); 
// This fxn allows plugins to insert themselves/scripts/css/files (right here) into the footer of your website. 
// Removing this fxn call will disable all kinds of plugins. 
// Move it if you like, but keep it around.
?>


      
 
    <footer class="bg-dark py-5">
	
	
	
		<?php 
    $footer_image = get_field('footer_image', 2);
    if (!empty($footer_image)):
      $image_url = $footer_image['sizes']['large'];
      $size = 'large';
      $width = $footer_image['sizes'][$size . '-width'];
      $height = $footer_image['sizes'][$size . '-height'];
  ?>
    <img loading="lazy" class="img-fluid footer-bg-image" width="<?php echo $width; ?>" height="<?php echo $height; ?>" src="<?php echo $image_url; ?>" alt="<?php echo esc_attr($footer_image['alt']); ?>" />
  <?php endif; ?>
  
  
  <div class="container">
    <div class="row mt-5">
      <div class="col-md-4 mb-5">
	  
	  
	  
        
<?php
$logolight = get_field('logo_light' , 2);


// Get the image size dynamically
$image = $logolight;
$size = 'medium';

// Allow logo height to be controlled via ACF
$logo_height = get_field('logo_height', 2);
$fixed_height = $logo_height ? intval($logo_height) : 40;

// Ensure image data exists
if (!empty($image) && isset($image['sizes'][$size])) {
 $image_url = $image['sizes'][$size];
 $width = isset($image['sizes'][$size . '-width']) ? $image['sizes'][$size . '-width'] : null;
 $height = isset($image['sizes'][$size . '-height']) ? $image['sizes'][$size . '-height'] : null;

 // Calculate proportional width if dimensions are valid
 if ($width && $height) {
 $new_width = round(($fixed_height / $height) * $width);
 }
}

?>
	  
        <img loading="lazy" class="logo mb-4 d-inline-block align-top" src="<?php echo $logolight['sizes']['medium']; ?>" width="<?php echo esc_attr($new_width); ?>" height="<?php echo esc_attr($fixed_height); ?>" title="<?php bloginfo('name'); ?> Logo" alt="<?php bloginfo('name'); ?> Logo" />
		
        <p class="text-white">
          <?php the_field('description', 2); ?>
        </p>

        <ul class="list-unstyled d-flex social-media-links">
          <?php if( have_rows('social_media', 2) ) : ?>
            <?php while( have_rows('social_media', 2) ) : the_row(); ?>
              <?php $link = get_sub_field('link'); ?>
              <li class="me-3">
                <a class="text-white" target="_blank" href="<?php echo esc_url($link); ?>">
                  <i class="fa <?php the_sub_field('icon'); ?>"></i>
                </a>
              </li>
            <?php endwhile; ?>
          <?php endif; ?>
        </ul>
      </div>

      <div class="col-md-8">
        <div class="row">
          <?php if( have_rows('footer_menu', 2) ) : ?>
            <?php while( have_rows('footer_menu', 2) ) : the_row(); ?>
              <div class="col-sm-4 mb-3">
                <h5 class="text-white mb-3">
                  <?php the_sub_field('title'); ?>
                </h5>
                <ul class="list-unstyled">
                  <?php if( have_rows('links') ) : ?>
                    <?php while( have_rows('links') ) : the_row(); ?>
                      <li>
                        <a class="text-white-50" href="<?php the_sub_field('link'); ?>">
                          <?php the_sub_field('title'); ?>
                        </a>
                      </li>
                    <?php endwhile; ?>
                  <?php endif; ?>
                </ul>
              </div>
            <?php endwhile; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  
  <div class="container border-top border-secondary pt-4 mt-5">
    <div class="row">
      <div class="col-md-6">
        <p class="text-white-50 mb-0">
          &copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. All Rights Reserved.
        </p>
      </div>
      <div class="col-md-6 text-md-end">
        <ul class="list-unstyled d-flex justify-content-md-end justify-content-start">
          <li class="ms-3">
            <a class="text-white-50" href="<?php the_field('privacy_policy_link', 2); ?>">
              Privacy Policy
            </a>
          </li>
          <li class="ms-3">
            <a class="text-white-50" href="<?php the_field('terms_and_conditions_link', 2); ?>">
              Terms & Conditions
            </a>
          </li>
        </ul>
      </div>
    </div>
  </div>
</footer>

<div class="cookie-consent-bar text-center bg-dark text-white p-3 fixed-bottom" style="display:none;">
    This website uses cookies to ensure you get the best experience. <a href="<?php the_field('privacy_policy_link', 2); ?>" class="text-primary">Learn more</a>
    <button id="accept-cookies" class="btn btn-sm btn-primary ms-3">Accept</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-Hww3L+H3C9aYqD6Wn+T5hXl0u+0K1lM0kE5LzD8n5V2+X5iS1z5eJ6V3sC9m0T9" crossorigin="anonymous"></script>

<script>
    // --- START: Critical Menu Fix for Cart/Checkout ---
    // isWcWhitePage is defined in header.php. If true, we skip dynamic transparency/scroll effects.
    if (typeof isWcWhitePage === 'boolean' && isWcWhitePage) {
        console.log("WooCommerce Fix: Skipping dynamic menu transparency/scroll effects.");
        // We stop all execution of any dynamic menu script here to ensure the PHP's fixed style sticks.
    } else {
        // Original dynamic menu logic for non-WC pages that are meant to be transparent.
        document.addEventListener('DOMContentLoaded', () => {
            const navbar = document.querySelector('.navbar');
            const logoBlack = document.querySelector('img.logo[src*="logoblack"]');
            const logoLight = document.querySelector('img.logo[src*="logolight"]');

            if (navbar && navbar.classList.contains('bg-transparent')) {
                const updateNavbar = () => {
                    if (window.scrollY > 50) {
                        navbar.classList.remove('bg-transparent', 'text-white');
                        navbar.classList.add('shadow', 'bg-light');
                        if (logoLight && logoBlack) {
                            logoLight.style.display = 'none';
                            logoBlack.style.display = 'inline-block';
                        }
                    } else {
                        navbar.classList.remove('shadow', 'bg-light');
                        navbar.classList.add('bg-transparent', 'text-white');
                        if (logoLight && logoBlack) {
                            logoLight.style.display = 'inline-block';
                            logoBlack.style.display = 'none';
                        }
                    }
                };
                window.addEventListener('scroll', updateNavbar);
                updateNavbar(); // Initial check
            }
        });
    }
    // --- END: Critical Menu Fix for Cart/Checkout ---
    
    // Cookie consent logic
    document.addEventListener('DOMContentLoaded', function() {
        const consentBar = document.querySelector('.cookie-consent-bar');
        const acceptButton = document.getElementById('accept-cookies');

        if (localStorage.getItem('cookie_consent') !== 'accepted') {
            if (consentBar) {
                consentBar.style.display = 'block';
            }
        }

        if (acceptButton) {
            acceptButton.addEventListener('click', function() {
                localStorage.setItem('cookie_consent', 'accepted');
                if (consentBar) {
                    consentBar.style.display = 'none';
                }
            });
        }
    });

    // Copy to clipboard logic
    document.addEventListener('DOMContentLoaded', function() {
        const copyButtons = document.querySelectorAll('.copy-to-clipboard');
        copyButtons.forEach(button => {
            button.addEventListener('click', function() {
                const textToCopy = this.getAttribute('data-clipboard-text');
                if (textToCopy) {
                    navigator.clipboard.writeText(textToCopy).then(() => {
                        // Optional: Show a temporary success message to the user
                        console.log('Link copied to clipboard!');
                    }).catch(err => {
                        console.error('Could not copy text: ', err);
                    });
                }
            });
        });
    });

</script>

<script async defer src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=marker" onload="waitForGoogleMaps()"></script>
<script>
    
    // --- Google Maps Initialization Script (Original) ---
    function base64ToArrayBuffer(base64) {
      const binary_string = window.atob(base64);
      const len = binary_string.length;
      const bytes = new Uint8Array(len);
      for (let i = 0; i < len; i++) {
        bytes[i] = binary_string.charCodeAt(i);
      }
      return bytes.buffer;
    }

    function pcmToWav(pcm16, sampleRate = 24000) {
      const buffer = new ArrayBuffer(44 + pcm16.length * 2);
      const view = new DataView(buffer);
      
      const writeString = (view, offset, string) => {
        for (let i = 0; i < string.length; i++) {
          view.setUint8(offset + i, string.charCodeAt(i));
        }
      };

      let offset = 0;
      
      // RIFF identifier
      writeString(view, offset, 'RIFF'); offset += 4;
      // file size
      view.setUint32(offset, 36 + pcm16.length * 2, true); offset += 4;
      // RIFF type
      writeString(view, offset, 'WAVE'); offset += 4;
      // format chunk identifier
      writeString(view, offset, 'fmt '); offset += 4;
      // format chunk length
      view.setUint32(offset, 16, true); offset += 4;
      // sample format (1 = PCM)
      view.setUint16(offset, 1, true); offset += 2;
      // number of channels
      view.setUint16(offset, 1, true); offset += 2;
      // sample rate
      view.setUint32(offset, sampleRate, true); offset += 4;
      // byte rate (SampleRate * NumChannels * BitsPerSample/8)
      view.setUint32(offset, sampleRate * 1 * 2, true); offset += 4;
      // block align (NumChannels * BitsPerSample/8)
      view.setUint16(offset, 1 * 2, true); offset += 2;
      // bits per sample
      view.setUint16(offset, 16, true); offset += 2;
      // data chunk identifier
      writeString(view, offset, 'data'); offset += 4;
      // data chunk length
      view.setUint32(offset, pcm16.length * 2, true); offset += 4;

      // Write PCM data
      const pcm16View = new Int16Array(buffer, offset);
      pcm16View.set(pcm16);

      return new Blob([buffer], { type: 'audio/wav' });
    }

    async function initMap() {
      try {
        const { Map } = await google.maps.importLibrary("map");
        const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");

        const locations = [
          <?php 
          $sum_lat = 0;
          $sum_lng = 0;
          $total = 0;

          if (have_rows('map_locations', 2)) : 
            while (have_rows('map_locations', 2)) : the_row(); 
              $location = get_sub_field('location');
              $icon = get_sub_field('icon');
              $url = get_sub_field('link');
              $title = get_sub_field('title');

              if (!empty($location)) {
                $sum_lat += $location['lat'];
                $sum_lng += $location['lng'];
                $total++;
                echo "{ position: { lat: " . esc_js($location['lat']) . ", lng: " . esc_js($location['lng']) . " }, icon: '" . esc_js($icon) . "', url: '" . esc_js($url) . "', title: '" . esc_js($title) . "' },\n";
              }
            endwhile; 
          endif; 
          ?>
        ];

        const mapElement = document.getElementById("map");
        if (!mapElement) {
            console.warn("Map element not found, skipping map initialization.");
            return;
        }

        const mapCenter = {
          lat: <?php echo $total ? round($sum_lat / $total, 6) : 24.7136; ?>,
          lng: <?php echo $total ? round($sum_lng / $total, 6) : 46.6753; ?>
        };

        const map = new google.maps.Map(mapElement, {
          center: mapCenter,
          zoom: 5,
          mapId: "DEMO_MAP_ID"
        });

        locations.forEach(loc => {
          const iconImage = document.createElement("img");
          iconImage.src = loc.icon;
          iconImage.style.width = "40px";
          iconImage.style.height = "40px";
          iconImage.alt = loc.title || "Map Marker";

          const marker = new google.maps.marker.AdvancedMarkerElement({
            map,
            position: loc.position,
            content: iconImage,
            title: loc.title
          });

          marker.addListener("gmp-click", () => {
            if (loc.url) {
              window.open(loc.url, '_blank');
            }
          });
        });




      } catch (error) {
        console.error("Error in initMap:", error);
      }
    }

    function waitForGoogleMaps(retries = 10, delay = 500) {
      if (typeof google !== 'undefined' && google.maps && google.maps.importLibrary) {
        initMap();
        return;
      }

      if (retries > 0) {
        setTimeout(() => waitForGoogleMaps(retries - 1, delay), delay);
      } else {
        console.error("Google Maps API did not load after multiple retries.");
      }
    }
</script>
</body>
</html>
