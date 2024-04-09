<?php
/*
Plugin Name: Easter Egg Plugin
Description: Adds an Easter egg hunt feature to your WordPress site, with hardcoded egg positions and encrypted cookie-based user tracking.
Version: 1.0
Author: Viktor :)
*/

// Encryption and Decryption functions for cookie data
$release_time = 0; // Unix timestamp for when the plugin should be released

function encrypt_data($data, $key) {
    return openssl_encrypt($data, 'AES-256-CBC', $key, 0, $key);
}

function decrypt_data($data, $key) {
    $decrypted = openssl_decrypt($data, 'AES-256-CBC', $key, 0, $key);
    if ($decrypted === FALSE) {
        $decrypted = "OpenSSL error: " . openssl_error_string();
    }
    return $decrypted;
}

// 'type' 1: Static egg, 2: Inherited egg by id, 3: Clickable egg, 4: Inherited egg by class
$eggs = [
    [
        'id' => '1', // The ID of the egg. Is just to track it.
        'type' => '2', // The type of the egg, described above.
        'x' => 'unset', // The left: value .Should be tinkered with, to ensure the best placement.
        'y' => 'unset', // The up: value. Should be tinkered with, to ensure the best placement.
        'z-index' => 0, // The z-index.
        'inherited-id' => '', // If the egg is type 2, this is the value of the inherited div.
        'inherited-class' => '', // If the egg is type 4, this is the value of the inherited div.
        'page_id' => 1, // The page that the egg should be displayed at.
        'image_url' => 'IMAGE_URL', // The image URL of the egg.
        'width' => '10px', // The width of the egg.
        'set-z-index-of-parent' => true, // IDK really what this is...
        'hint_info' => 'HINT', // The hint that should be displayed on the hint page.
        'hint_release' => 0, // The release timestamp of the egg.
        'alternate-click-method' => true, // If the egg should use an alternative click type, that instead of checking the div for a click, it checks the screen.
        'golden' => true, // If the egg should be golden or not.
        'golden_giftcard' => 'GIFTCARD_CODE' // The gift card code of the golden egg, if used.
    ],
    // Add more eggs with unique 'id', 'x', 'y', 'z-index', 'page_id', and 'image_url'
];


// Add action hooks for AJAX requests
add_action('wp_ajax_update_user_progress', 'update_user_progress');
add_action('wp_ajax_nopriv_update_user_progress', 'update_user_progress');

function update_user_progress() {
    // Check if egg_id is set
    if (isset($_POST['egg_id'])) {
        $egg_id = $_POST['egg_id'];
    } else {
        echo 'No egg ID provided.';
        wp_die();
    }

    if(is_golden($egg_id)) {
        setcookie('golden_egg_giftcard', get_egg_by_id($egg_id)['golden_giftcard'], time() + 3600 * 24 * 30, '/');
        echo 'Golden egg found!';
    }

    $key = 'secret_key'; // Use a secure method to generate and store this key
    $decrypted_progress = isset($_COOKIE['egg_hunt_progress']) ? json_decode(decrypt_data($_COOKIE['egg_hunt_progress'], $key), true) : array();
    if (!in_array($egg_id, $decrypted_progress, true)) {
        $decrypted_progress[] = $egg_id;
        setcookie('egg_hunt_progress', encrypt_data(json_encode($decrypted_progress), $key), time() + 3600 * 24 * 30, '/'); // 30-day cookie
        echo 'User progress updated successfully.';
        log_egg($egg_id);
    } else {
        echo 'Egg already found.';
    }

    wp_die();
}

function get_golden_egg_giftcard() {
    return isset($_COOKIE['golden_egg_giftcard']) ? $_COOKIE['golden_egg_giftcard'] : '';
}

function get_user_progress() {
    $key = 'secret_key';
    return isset($_COOKIE['egg_hunt_progress']) ? json_decode(decrypt_data($_COOKIE['egg_hunt_progress'], $key), true) : array();
}

function filter_golden_eggs() {
    global $eggs;
    $caught_eggs = get_caught_eggs_from_log();
    $golden_eggs = array();
    foreach ($eggs as $egg) {
        if (isset($egg['golden']) && $egg['golden'] === true) {
            if (in_array($egg['id'], $caught_eggs)) {
                $egg['found'] = true;
            }
            $golden_eggs[] = $egg;
            //remove the golden egg from the array
            $eggs = array_filter($eggs, function($e) use ($egg) {
                return $e['id'] !== $egg['id'];
            });
        }
    }
    return $golden_eggs;
}

function is_golden_egg_found($egg_id) {
    global $eggs;
    $caught_eggs = get_caught_eggs_from_log();
    foreach ($eggs as $egg) {
        if ($egg['id'] == $egg_id) {
            if (isset($egg['golden']) && $egg['golden'] === true) {
                return in_array($egg_id, $caught_eggs);
            }
        }
    }
    return false;
}


function get_caught_eggs_from_log() {
    $log_file = plugin_dir_path(__FILE__) . 'egg_log.json';
    $log = json_decode(file_get_contents($log_file), true);
    if ($log === NULL) {
        return array();
    }
    $caught_eggs = array();
    foreach ($log as $entry) {
        $caught_eggs[] = $entry['egg_id'];
    }
    return $caught_eggs;
}

function log_egg($egg_id) {
    $log_file = plugin_dir_path(__FILE__) . 'egg_log.json';
    $log = json_decode(file_get_contents($log_file), true);
    if ($log === NULL) {
        $log = array();
    }
    $log[] = array(
        'egg_id' => $egg_id,
        'timestamp' => time()
    );
    file_put_contents($log_file, json_encode($log));
}

function is_golden($egg_id) {
    global $eggs;
    foreach ($eggs as $egg) {
        if ($egg['id'] == $egg_id) {
            return isset($egg['golden']) && $egg['golden'] === true;
        }
    }
    return false;
}

function get_egg_by_id($egg_id) {
    global $eggs;
    foreach ($eggs as $egg) {
        if ($egg['id'] == $egg_id) {
            return $egg;
        }
    }
    return NULL;
}


function calculate_coupon($egg_count) {
    //HERE THE COUPON IS DETERMINED, FROM HOW MANY EGGS ARE FOUND.
    $coupon = "";
    if ($egg_count >= 1) {
        $discount = "GLAD5PASK";
    }
    if ($egg_count >= 3) {
        $discount = "PASKJAKT10";
    }
    if ($egg_count >= 5) {
        $discount = "PASK15HARE";
    }
    if ($egg_count >= 8) {
        $discount = "PASKAGG20";
    }
    if ($egg_count >= 10) {
        $discount = "GLADPASK25";
    }
    return $discount;
}

function calculate_discount($egg_count) {
    //HERE THE DISPLAYED DISCOUNT IS CALCULATED, BASED ON THE AMOUNT OF EGGS FOUND.
    $discount = 0;
    if ($egg_count >= 1) {
        $discount = 5;
    }
    if ($egg_count >= 3) {
        $discount = 10;
    }
    if ($egg_count >= 5) {
        $discount = 15;
    }
    if ($egg_count >= 8) {
        $discount = 20;
    }
    if ($egg_count >= 10) {
        $discount = 25;
    }
    return $discount;
}

function calculate_next_discount($egg_count) {
    // Calculate the discount based on the number of eggs found
    $discount = 0;
    if ($egg_count < 10) {
        $discount = 25;
    }
    if ($egg_count < 8) {
        $discount = 20;
    }
    if ($egg_count < 5) {
        $discount = 15;
    }
    if ($egg_count < 3) {
        $discount = 10;
    }
    if ($egg_count < 1) {
        $discount = 5;
    }
    return $discount;
}

function calculate_eggs_until_next_discount($egg_count) {
    $eggs_until_next_discount = 0;
    if ($egg_count < 10) {
        $eggs_until_next_discount = 10 - $egg_count;
    }
    if ($egg_count < 8) {
        $eggs_until_next_discount = 8 - $egg_count;
    }
    if ($egg_count < 5) {
        $eggs_until_next_discount = 5 - $egg_count;
    }
    if ($egg_count < 3) {
        $eggs_until_next_discount = 3 - $egg_count;
    }
    if ($egg_count < 1) {
        $eggs_until_next_discount = 1;
    }
    return $eggs_until_next_discount;
}

// Enqueue scripts and styles
function enqueue_egg_hunt_scripts() {
    wp_enqueue_script('egg-hunt-js', plugin_dir_url(__FILE__) . 'egg_hunt.js', array('jquery'), null, true);
    wp_localize_script('egg-hunt-js', 'eggHuntAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('egg_hunt_nonce')
    ));
    wp_enqueue_style('egg-hunt-css', plugin_dir_url(__FILE__) . 'egg_hunt.css');
}
add_action('wp_enqueue_scripts', 'enqueue_egg_hunt_scripts');

function render_eggs() {
    global $eggs;
    global $release_time;
    if ($release_time > time()) {
        return;
    }
    $current_page_id = get_the_ID();  // Get the current page ID

    // Extended hardcoded positions for eggs with 'page_id' and 'image_url'
    $output = '<div class="egg-container">';
    foreach ($eggs as $egg) {
        if ($egg['page_id'] == $current_page_id) {  // Check if the egg belongs to the current page
            if (is_golden_egg_found($egg['id'])) {
                continue;
            }
            if ($egg['type'] == 2) {
                $output .= "<script>jQuery(document).ready(function($) { 
                $('#{$egg['inherited-id']}').append('<div class=\"egg\" style=\"position: absolute; z-index:{$egg['z-index']}; left:{$egg['x']}; top:{$egg['y']}; width:{$egg['width']};\" data-egg-id=\"{$egg['id']}\"><img src=\"{$egg['image_url']}\" alt=\"Egg {$egg['id']}\" /></div>');
                if ('{$egg['set-z-index-of-parent']}' == true) {
            $('#{$egg['inherited-id']}').parent().css('z-index', '0');
            console.log($('#{$egg['inherited-id']}').parent());
        }
    });</script>";
            }
            elseif ($egg['type'] == 4) {
                if("{$egg['alternate-click-method']}") {
                    $output .= "<script>
jQuery(document).ready(function($) {
    $('.{$egg['inherited-class']}').append('<div class=\"egg\" style=\"position: absolute; z-index: {$egg['z-index']}; left:{$egg['x']}; top:{$egg['y']}; width:{$egg['width']}\" data-egg-id=\"{$egg['id']}\" data-inherit-id=\"{$egg['inherited-id']}\"><img src=\"{$egg['image_url']}\" alt=\"Egg {$egg['id']}\" /></div>');
    eggElement = $('.egg[data-egg-id={$egg['id']}]');
        $(document).click(function(e) {
            console.log('click');
        var clickX = e.clientX + $(window).scrollLeft();
        var clickY = e.clientY + $(window).scrollTop();

        $(eggElement).each(function() {
            console.log('click');
            var eggOffset = $(this).offset();
            var eggX = eggOffset.left;
            var eggY = eggOffset.top;
            var eggWidth = $(this).outerWidth();
            var eggHeight = $(this).outerHeight();

            if (clickX > eggX && clickX < eggX + eggWidth && clickY > eggY && clickY < eggY + eggHeight) {
                var eggId = $(this).data('egg-id');
                alert('Grattis, du hittade ägg #' + eggId + '!');
                $.ajax({
                    url: eggHuntAjax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'update_user_progress',
                        egg_id: eggId
                    },
                    success: function(response) {
                        console.log(response);
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('ERROR:');
                        console.error(textStatus, errorThrown);
                    }
                });
            }
        });
    });
});
                    </script>";
                } else {
                    $output .= "<script>jQuery(document).ready(function($) { $('.{$egg['inherited-class']}').append('<div class=\"egg\" style=\"position: absolute; z-index: {$egg['z-index']}; left:{$egg['x']}; top:{$egg['y']}; width:{$egg['width']};\" data-egg-id=\"{$egg['id']}\" data-inherit-id=\"{$egg['inherited-id']}\"><img src=\"{$egg['image_url']}\" alt=\"Egg {$egg['id']}\" /></div>'); });</script>";
                }
            }
            elseif ($egg['type'] == 3) {
                $output .= "<script>
    jQuery(document).ready(function($) {
        let eggElement = null;
        clickedtimes = 0;
        $('#{$egg['clicked-id']}').click(function(e) {
                let clickamount = {$egg['click-amount']};
                console.log(clickamount);
                clickedtimes++;
                //check if half of the clicks are done, or more, ie clickamount/2
                if (clickedtimes === clickamount/2) {
                    //Spawn the egg, in the clicked-id div
                    $('#{$egg['clicked-id']}').append('<div class=\"egg\" style=\"position: absolute; z-index: {$egg['z-index']};\" data-egg-id=\"{$egg['id']}\" data-inherit-id=\"{$egg['inherited-id']}\" data-offset-x=\"{$egg['x']}\" data-offset-y=\"{$egg['y']}; width:{$egg['width']}\"><img src=\"{$egg['image_url']}\" alt=\"Egg {$egg['id']}\" /></div>');
                    eggElement = $('.egg[data-egg-id={$egg['id']}]');
                }
        else if (clickedtimes > clickamount/2 && clickedtimes < clickamount) {
            // Calculate the total distance to move the egg
            let totalDistanceX = parseFloat('{$egg['final-left']}') * parseFloat(getComputedStyle(document.documentElement).fontSize) - parseFloat(eggElement.css('left'));
            let totalDistanceY = parseFloat('{$egg['final-top']}') * parseFloat(getComputedStyle(document.documentElement).fontSize) - parseFloat(eggElement.css('top'));

            // Calculate the distance to move the egg per click
            let distancePerClickX = totalDistanceX / (clickamount / 2);
            let distancePerClickY = totalDistanceY / (clickamount / 2);

            // Calculate the new position of the egg
            let newX = parseFloat(eggElement.css('left')) + distancePerClickX * (clickedtimes - clickamount / 2);
            let newY = parseFloat(eggElement.css('top')) + distancePerClickY * (clickedtimes - clickamount / 2);

            // Convert the new position from pixels to em
            newX = newX / parseFloat(getComputedStyle(document.documentElement).fontSize);
            newY = newY / parseFloat(getComputedStyle(document.documentElement).fontSize);

            // Move the egg to the new position
            if ('{$egg['final-left']}' !== '0') {
                eggElement.animate({
                    left: newX+'em'
                }, 500); // Adjust the duration as needed
            }
            if ('{$egg['final-top']}' !== '0') {
                eggElement.animate({
                    top: newY+'em'
                }, 500); // Adjust the duration as needed
            }
        }
        if(clickedtimes === clickamount) {
            if('{$egg['swap-z-index']}' === true) {
                eggElement.css('z-index', 0);
            }
        }
                
               });
    $(document).click(function(e) {
        var clickX = e.clientX + $(window).scrollLeft();
        var clickY = e.clientY + $(window).scrollTop();

        $(eggElement).each(function() {
            var eggOffset = $(this).offset();
            var eggX = eggOffset.left;
            var eggY = eggOffset.top;
            var eggWidth = $(this).outerWidth();
            var eggHeight = $(this).outerHeight();

            if (clickX > eggX && clickX < eggX + eggWidth && clickY > eggY && clickY < eggY + eggHeight) {
                var eggId = $(this).data('egg-id');
                alert('Grattis, du hittade ägg #' + eggId + '!');
                $.ajax({
                    url: eggHuntAjax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'update_user_progress',
                        egg_id: eggId
                    },
                    success: function(response) {
                        console.log(response);
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('ERROR:');
                        console.error(textStatus, errorThrown);
                    }
                });
            }
        });
    });
        
    });
</script>";

            }
            else {
                $output .= "<div class='egg' data-egg-id='{$egg['id']}' style='position:absolute; left:{$egg['x']}; top:{$egg['y']}; z-index:{$egg['z-index']}; width:{$egg['width']}'>";
                $output .= "<img src='{$egg['image_url']}' alt='Egg {$egg['id']}' /></div>";  // Use the egg image
            }
        }
    }
    $output .= '</div>';

    // JavaScript for interaction
    $output .= '<script>
        jQuery(document).ready(function($) {
            $(".egg").click(function() {
                var eggId = $(this).data("egg-id");
                // Implement logic to mark the egg as found
                alert("Grattis, du hittade ägg #" + eggId + "!");
                
                // Make an AJAX request to update user progress
                $.ajax({
                    url: eggHuntAjax.ajax_url, // URL of the admin-ajax.php file
                    type: "POST",
                    data: { 
                        action: "update_user_progress", // The action hook that calls the update_user_progress function
                        egg_id: eggId 
                    },
                    success: function(response) {
                    // Handle the response from the server
                    console.log(response);
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                    // Handle any errors
                    console.error("ERROR:");
                    console.error(textStatus, errorThrown);
                    }
                });
            });
        });
    </script>';

    return $output;
}
add_shortcode('render_eggs', 'render_eggs');  // Use the shortcode [render_eggs] to display eggs


function easter_egg_site() {
    global $eggs;
    global $release_time;
    $golden_eggs = filter_golden_eggs();
    $found_golden_eggs = 0;
    $found_eggs = get_user_progress();
    $discount = calculate_discount(count($found_eggs));
    $next_discount = calculate_next_discount(count($found_eggs));
    $eggs_until_next_discount = calculate_eggs_until_next_discount(count($found_eggs));
    $coupon = calculate_coupon(count($found_eggs));
    $has_giftcard = isset($_COOKIE['golden_egg_giftcard']) && !empty(trim($_COOKIE['golden_egg_giftcard']));
    $giftcard_code = get_golden_egg_giftcard();
    if ($release_time > time()) {
        return;
    }
    foreach ($golden_eggs as $golden_egg) {
        $is_found = $golden_egg['found'];
        if ($is_found) {
            $found_golden_eggs++;
        }
    }

    ?>
    <div class="easter-eggs">
        <div class="fadeIn" id="overlay" style="display: none">
            <div id="popup-egg" style="">
                <div class="progress-section" id="popup-progress">
                    <div id="progress-indicator">
                        <div id="progress-bar" style="width: <?php echo count($found_eggs) / count($eggs) * 100; ?>%;"></div>
                        <div id="step1" class="progress-step" style="left: 10%;"></div>
                        <div id="step2" class="progress-step" style="left: 30%;"></div>
                        <div id="step3" class="progress-step" style="left: 50%;"></div>
                        <div id="step4" class="progress-step" style="left: 80%;"></div>
                        <div id="step6" class="progress-step" style="left: 98%;"></div>
                        <!-- Adjust positions and number of steps as needed -->
                    </div>
                </div>
                <div id="coupon-display" style="display: none;">
                    <p id="coupon-text">Din rabattkod är: <?php echo $coupon;?></p>
                </div>
                <div class="popup-content_egg-redeem">
                    <p>Du har hittat <?php echo count($found_eggs);?> av <?php echo count($eggs);?> ägg. Detta kommer ge dig en rabattkod på <?php echo $discount;?>%. Om du hittar <?php echo $eggs_until_next_discount;?> ägg till kommer rabatten bli <?php echo $next_discount;?>%. Vill du fortsätta leta, eller ta rabattkoden på <?php echo $discount;?>%?</p>
                    <button id="egg-continue-button" onclick="function exitPopup() {
                    var overlay = document.getElementById('overlay');
                    var popupEgg = document.getElementById('popup-egg');

                    overlay.style.opacity = '0';
                    popupEgg.style.opacity = '0';

                    setTimeout(function() {
                        overlay.style.display = 'none';
                        popupEgg.style.display = 'none';
                    }, 500); // match this with the duration of your transition
                }
                exitPopup()">Fortsätt leta ägg</button>
                    <button onclick="function claimDiscount() {
                    var progressBar = document.getElementById('popup-progress');
                    var claimPrize = document.getElementsByClassName('popup-content_egg-redeem')[0];
                    var couponDisplay = document.getElementById('coupon-display');

                    progressBar.style.display = 'none';
                    claimPrize.style.display = 'none';
                    couponDisplay.style.display = 'block';
                }
                claimDiscount()">Konvertera ägg till rabattkod</button>
                </div>
                <button id="egg-exit-button" onclick="function exitPopup() {
                    var overlay = document.getElementById('overlay');
                    var popupEgg = document.getElementById('popup-egg');

                    overlay.style.opacity = '0';
                    popupEgg.style.opacity = '0';

                    setTimeout(function() {
                        overlay.style.display = 'none';
                        popupEgg.style.display = 'none';
                    }, 500); // match this with the duration of your transition
                }
                exitPopup()">✖</button>
            </div>
            <div id="info-popup-egg" style="display: none;">
                //TODO ADD INFO CONTENT
            </div>
        </div>
        <div class="progress-section">
            <div id="progress-indicator">
                <div id="progress-bar" style="width: <?php echo count($found_eggs) / count($eggs) * 100; ?>%;"></div>
                <div id="step1" class="progress-step" style="left: 10%;"></div>
                <div id="step2" class="progress-step" style="left: 30%;"></div>
                <div id="step3" class="progress-step" style="left: 50%;"></div>
                <div id="step4" class="progress-step" style="left: 80%;"></div>
                <div id="step6" class="progress-step" style="left: 98%;"></div>
                <!-- Adjust positions and number of steps as needed -->
                <div id="progress-text">Funna ägg: <?php echo count($found_eggs) . '/' . count($eggs); ?></div>
            </div>
        </div>
        <div id="claim-prize">
            <button id="claim-prize-button" onclick="function showPopup() {
                var overlay = document.getElementById('overlay');
                var popupEgg = document.getElementById('popup-egg');

                overlay.style.display = 'flex';
                popupEgg.style.display = 'block';

                setTimeout(function() {
                    overlay.style.opacity = '1';
                    popupEgg.style.opacity = '1';
                }, 100); // match this with the duration of your transition
            }
            showPopup()">Hämta pris</button>
        </div>
    </div>
    <div class="egg_display">
        <?php
        $mixup = 0;
        foreach ($eggs as $egg) {
            $mixup++;
            $mixupcolor = "#eecb67";
            if ($mixup % 2 == 0) {
                $mixupcolor = "#eccfa2";
            }
            $is_found = in_array($egg['id'], $found_eggs);
            $countdown_seconds = max(0, $egg['hint_release'] - time());
            $countdown_minutes = 0;
            if ($countdown_seconds >= 60) {
                $countdown_minutes = floor($countdown_seconds / 60);
            }
            $countdown_hours = 0;
            if ($countdown_minutes >= 60) {
                $countdown_hours = floor($countdown_minutes / 60);
            }
            ?>
            <div class="display-box" id="<?php echo $egg['id'];?>" style="background-color: <?php echo $mixupcolor;?>;">
                <div class="overlay-egg_box" style="<?php echo $is_found ? '' : 'display: none;'; ?>"></div>
                <div class="egg-found" style="<?php echo $is_found ? '' : 'display: none;'; ?>">Du har hittat det här ägget!</div>
                <div class="egg-id">Ägg <?php echo $egg['id'];?></div>
                <div class="hint-container">
                    <p class="hint-release" id="hint-<?php echo $egg['id'];?>">Ledtråden släpps om: <span id="countdown-<?php echo $egg['id'];?>">
                <?php echo isset($countdown_minutes) ? sprintf("%02d:%02d", $countdown_minutes, $countdown_seconds % 60) : "00:00"; ?>
            </span></p>
                    <button class="hint-button">Visa ledtråd</button>
                </div>
            </div>
            <script>
                <?php
                if ($egg['hint_release'] <= time()) {
                ?>
                //make the hint button trigger an alert with the hint
                document.getElementById('<?php echo $egg['id'];?>').querySelector('.hint-button').addEventListener('click', function() {
                    alert('<?php echo $egg['hint_info'];?>');
                });
                <?php
                } else {
                ?>
                document.getElementById('<?php echo $egg['id'];?>').querySelector('.hint-button').addEventListener('click', function() {
                    alert('Ledtråden är inte tillgänglig än.');
                });
                <?php
                }
                ?>
                // Countdown timer logic for each egg
                var countdown<?php echo $egg['id'];?> = <?php echo $countdown_seconds;?>;
                var countdownInterval<?php echo $egg['id'];?> = setInterval(function() {
                    var minutes = Math.floor(countdown<?php echo $egg['id'];?> / 60);
                    var seconds = countdown<?php echo $egg['id'];?> % 60;
                    document.getElementById('countdown-<?php echo $egg['id'];?>').textContent = (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
                    countdown<?php echo $egg['id'];?>--;
                    if (countdown<?php echo $egg['id'];?> < 0) {
                        clearInterval(countdownInterval<?php echo $egg['id'];?>);
                        document.getElementById('countdown-<?php echo $egg['id'];?>').textContent = '00:00';
                    }
                }, 1000);
            </script>
            <?php
        }
        ?>
    </div>
    <div class="golden-egg_display">
        <div class="golden-info">Gyllene ägg</div>
        <div class="golden-egg-info">Just nu finns det <?php echo count($golden_eggs) - $found_golden_eggs;?> av <?php echo count($golden_eggs);?> guldägg kvar att hitta!</div>
        <?php
        if ($has_giftcard) {
            ?>
            <div class="golden-giftcard">Ditt presentkort: <?php echo $giftcard_code;?></div>
            <?php
        }
        ?>
        <div class="golden-egg-container">
            <?php
            foreach ($golden_eggs as $golden_egg) {
                $is_found = $golden_egg['found'];
                ?>
                <div class="golden-display-box" id="<?php echo $golden_egg['id'];?>">
                    <img id="golden-egg-img" src="<?php echo $golden_egg['image_url'];?>" alt="Golden egg" class="golden-egg-image">
                    <div class="overlay-egg_box" style="<?php echo $is_found ? '' : 'display: none;'; ?>"></div>
                </div>
                <?php
            }
            ?>
        </div>
    </div>
    <?php
}
add_shortcode('easter_egg_site', 'easter_egg_site');
