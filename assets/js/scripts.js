jQuery(function ($) {
  jQuery('document').ready(function ($) {
    // Simple AJAX listeners
    $(document).bind("ajaxSend", function () {
      $('.btn-primary').attr('disabled', 'disabled');
    }).bind("ajaxComplete", function () {
      $('.btn-primary').removeAttr('disabled');
    });

    // Start quiz
    $('#start-quiz').click(function () {
      nextStep();
      $('.footer').addClass('animated fadeOutDown');
      $('.footer-quiz').addClass('animated fadeInUpBig').show();

      setTimeout(function () {
        $('.footer').hide();
      }, 300);

      return false;
    });

    // Allow users to go back
    $('#quiz-back').click(function () {
      previousStep();

      return false;
    });

    // styling for quiz choices
    $('.page input[type=radio]').each(function () {
      var self = $(this),
          label = self.next();

      label.remove();
      self.iCheck({
        checkboxClass: 'icheckbox_line',
        radioClass: 'iradio_line',
        insert: '<label><i class="fa fa-fw"></i> ' + label.text() + '</label>'
      });
    });

    // progress quiz when option is picked
    $('.page input[type=radio]').on('ifClicked', function () {
      setTimeout(function () {
        nextStep()
      }, 300);

      return false;
    });

    // Show quiz results modal
    $('#get-results').click(function () {
      $('#quiz-results').modal('show');

      return false;
    });

    // Submit quiz results
    $('#submit-results').click(function (e) {
      e.preventDefault();

      if (stepVerify('quiz') == 0) {
        var form = $('#seller-quiz');

        $.ajax({
          type: 'POST',
          url: SellerQuiz.ajaxurl,
          data: form.serialize(),
          dataType: 'json',
          async: true,
          success: function (response) {
            $('#first_name_2').val($('#first_name').val());
            $('#email_2').val($('#email').val());
            $('#quiz-results .modal-body').html('<h2><i class="fa fa-check-circle"></i> <br> <small>Success!</small>');
            $('#user_id').val(response.user_id);
            $('#quiz-back').hide();

            var retargeting = $('#retargeting').val(),
                conversion = $('#conversion').val();
            if (retargeting != '') {
              (function () {
                var _fbq = window._fbq || (window._fbq = []);
                if (!_fbq.loaded) {
                  var fbds = document.createElement('script');
                  fbds.async = true;
                  fbds.src = '//connect.facebook.net/en_US/fbds.js';
                  var s = document.getElementsByTagName('script')[0];
                  s.parentNode.insertBefore(fbds, s);
                  _fbq.loaded = true;
                }
                _fbq.push(['addPixelId', retargeting]);
              })();
              window._fbq = window._fbq || [];
              window._fbq.push(['track', 'PixelInitialized', {}]);
            }
            if (conversion != '') {
              (function () {
                var _fbq = window._fbq || (window._fbq = []);
                if (!_fbq.loaded) {
                  var fbds = document.createElement('script');
                  fbds.async = true;
                  fbds.src = '//connect.facebook.net/en_US/fbds.js';
                  var s = document.getElementsByTagName('script')[0];
                  s.parentNode.insertBefore(fbds, s);
                  _fbq.loaded = true;
                }
              })();
              window._fbq = window._fbq || [];
              window._fbq.push(['track', conversion, {'value': '0.00', 'currency': 'USD'}]);
            }

            setTimeout(function () {
              $('#quiz-results').modal('hide');
              $('#offer').html('<h2 class="quiz-completed"><i class="fa fa-check-circle"></i> <br> <strong>You scored ' + response.score + '/88</strong><br><small>' + response.feedback + '</small></h2> <a href="' + $('#valuator-link').val() + '" class="btn btn-primary btn-lg" id="show-offer">Click Here To See What Your Home Is Worth <br> <small>(Based On Official Data of Recently Sold Listings In Your Area)</small></a>');
              $('.quiz-page').animate({'padding-top': '6%'}, 500);
            }, 1000);
          }
        });
      }
    });
  });

  function stepVerify(step) {
    $('.help-block').remove();
    $('.form-group').removeClass('has-error');
    var count = 0;

    if (step === 'quiz') {
      var inputs = ["first_name", "email"];
    } else if (step === 'offer') {
      var inputs = ["last_name", "phone", "address", "city", "state", "zip_code"];
    }

    if (inputs !== undefined) {
      jQuery.each(inputs, function (i, id) {
        if ($("#" + id).val() === '') {
          stepError(id, 'You must enter a value.');
          count++;
        }
      });
    }

    // Advanced Section Specific Validation
    if (step === 'basics' && count === 0) {
      var emailregex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;

      if (!emailregex.test($('#email').val())) {
        stepError('email', 'Email address is not valid.');
        count++;
      }
    }

    function stepError(id, msg) {
      $("#" + id).parent().addClass('has-error');
      $("#" + id).after('<p class="help-block">' + msg + '</p>');
    }

    return count;
  }

  function nextStep() {
    var $active = $('.quiz-page .page:visible'),
        $next = $('.quiz-page .page:visible').next('.page'),
        $step = $active.find('.question-number').text();

    $active.removeClass('fadeIn fadeInUpBig fadeInDownBig fadeOutUpBig fadeOutDownBig').addClass('fadeOutUpBig');
    $next.removeClass('fadeIn fadeInUpBig fadeInDownBig fadeOutUpBig fadeOutDownBig').addClass('animated fadeInUpBig').show();

    setTimeout(function () {
      $active.hide();
    }, 500);

    if ($step == '1.') {
      $('#quiz-back').show();
    }

    if ($step == '') {
      $step = '0.';
    }

    updateProgressBar(parseInt($step.replace('.', ''), 10) + 1);
  }

  function previousStep() {
    var $active = $('.quiz-page .page:visible'),
        $prev = $('.quiz-page .page:visible').prev('.page'),
        $step = $active.find('.question-number').text();

    $active.removeClass('fadeIn fadeInUpBig fadeInDownBig fadeOutUpBig fadeOutDownBig').addClass('fadeOutDownBig');
    $prev.removeClass('fadeIn fadeInUpBig fadeInDownBig fadeOutUpBig fadeOutDownBig').addClass('animated fadeInDownBig').show();

    setTimeout(function () {
      $active.hide();
    }, 500);

    if ($step == '2.') {
      $('#quiz-back').hide();
    }

    if ($step == '') {
      $step = '17.';
    }

    updateProgressBar(parseInt($step.replace('.', ''), 10) - 1);
  }

  function updateProgressBar(step) {
    var progress = Math.ceil((step / 17) * 100);

    $('.progress-percent').text(progress);
    $('.progress-bar').attr('aria-valuenow', progress).css('width', progress + '%');
  }
});

