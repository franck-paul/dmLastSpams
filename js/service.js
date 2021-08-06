/*global $, dotclear */
'use strict';

dotclear.dmLastSpamsCount = function () {
  $.get('services.php', {
    f: 'dmLastSpamsCount',
    xd_check: dotclear.nonce,
  })
    .done(function (data) {
      if ($('rsp[status=failed]', data).length > 0) {
        // For debugging purpose only:
        // console.log($('rsp',data).attr('message'));
        window.console.log('Dotclear REST server error');
      } else {
        const nb_spams = Number($('rsp>check', data).attr('ret'));
        if (nb_spams !== undefined && nb_spams != dotclear.dmLastSpams_SpamCount) {
          dotclear.badge($('#dashboard-main #icons p a[href="comments.php"]'), {
            id: 'dmls',
            remove: nb_spams == 0,
            value: nb_spams,
            sibling: true,
            icon: true,
          });
          dotclear.dmLastSpams_SpamCount = nb_spams;
        }
      }
    })
    .fail(function (jqXHR, textStatus, errorThrown) {
      window.console.log(`AJAX ${textStatus} (status: ${jqXHR.status} ${errorThrown})`);
    })
    .always(function () {
      // Nothing here
    });
};

dotclear.dmLastSpamsCheck = function () {
  $.get('services.php', {
    f: 'dmLastSpamsCheck',
    xd_check: dotclear.nonce,
    last_id: dotclear.dmLastSpams_LastSpamId,
  })
    .done(function (data) {
      if ($('rsp[status=failed]', data).length > 0) {
        // For debugging purpose only:
        // console.log($('rsp',data).attr('message'));
        window.console.log('Dotclear REST server error');
      } else {
        const new_spams = Number($('rsp>check', data).attr('ret'));
        if (new_spams > 0) {
          // Get new list
          $.get('services.php', {
            f: 'dmLastSpamsRows',
            xd_check: dotclear.nonce,
            stored_id: dotclear.dmLastSpams_LastSpamId,
            last_id: $('rsp>check', data).attr('last_id'),
            last_counter: dotclear.dmLastSpams_LastCounter,
          })
            .done(function (data) {
              if ($('rsp[status=failed]', data).length > 0) {
                // For debugging purpose only:
                // console.log($('rsp',data).attr('message'));
                window.console.log('Dotclear REST server error');
              } else {
                if (Number($('rsp>rows', data).attr('ret')) > 0) {
                  // Display new comments
                  const xml = $('rsp>rows', data).attr('list');
                  // Replace current list with the new one
                  if ($('#last-spams ul').length) {
                    $('#last-spams ul').remove();
                  }
                  if ($('#last-spams p').length) {
                    $('#last-spams p').remove();
                  }
                  const counter = Number($('rsp>rows', data).attr('counter'));
                  if (counter > 0) {
                    dotclear.dmLastSpams_LastCounter = Number(dotclear.dmLastSpams_LastCounter) + counter;
                  }
                  $('#last-spams h3').after(xml);
                  if (dotclear.dmLastSpams_Badge) {
                    // Badge on module
                    dotclear.badge($('#last-spams'), {
                      id: 'dmls',
                      value: dotclear.dmLastSpams_LastCounter,
                      remove: dotclear.dmLastSpams_LastCounter == 0,
                    });
                  }
                  // Bind every new lines for viewing comment content
                  $.expandContent({
                    lines: $('#last-spams li.line'),
                    callback: dotclear.dmLastSpamsView,
                  });
                  $('#last-spams ul').addClass('expandable');
                }
              }
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
              window.console.log(`AJAX ${textStatus} (status: ${jqXHR.status} ${errorThrown})`);
            })
            .always(function () {
              // Nothing here
            });

          // Store last comment id
          dotclear.dmLastSpams_LastSpamId = $('rsp>check', data).attr('last_id');
        }
      }
    })
    .fail(function (jqXHR, textStatus, errorThrown) {
      window.console.log(`AJAX ${textStatus} (status: ${jqXHR.status} ${errorThrown})`);
    })
    .always(function () {
      // Nothing here
    });
};

dotclear.dmLastSpamsView = function (line, action, e) {
  action = action || 'toggle';
  if ($(line).attr('id') == undefined) {
    return;
  }

  const spamId = $(line).attr('id').substr(4);
  const lineId = `dmlse${spamId}`;
  let li = document.getElementById(lineId);

  // If meta key down display content rather than HTML code
  const clean = !e.metaKey;

  if (!li) {
    // Get comment content if possible
    dotclear.getCommentContent(
      spamId,
      function (content) {
        if (content) {
          li = document.createElement('li');
          li.id = lineId;
          li.className = 'expand';
          $(li).append(content);
          $(line).addClass('expand');
          line.parentNode.insertBefore(li, line.nextSibling);
        } else {
          // No content, content not found or server error
          $(line).removeClass('expand');
        }
      },
      {
        metadata: false,
        clean: clean,
      }
    );
  } else {
    $(li).toggle();
    $(line).toggleClass('expand');
  }
};

$(function () {
  Object.assign(dotclear, dotclear.getData('dm_lastspams'));
  $.expandContent({
    lines: $('#last-spams li.line'),
    callback: dotclear.dmLastSpamsView,
  });
  $('#last-spams ul').addClass('expandable');
  if (dotclear.dmLastSpams_AutoRefresh) {
    // Auto refresh requested : Set 30 seconds interval between two checks for new comments and spam counter check
    dotclear.dmLastSpams_Timer = setInterval(dotclear.dmLastSpamsCheck, 30 * 1000);
    if (dotclear.dmLastSpams_Badge) {
      $('#last-spams').addClass('badgeable');
      const icon_com = $('#dashboard-main #icons p a[href="comments.php"]');
      if (icon_com.length) {
        // First pass
        dotclear.dmLastSpamsCount();
        // Then fired every 30 seconds
        dotclear.dmLastSpams_TimerSpam = setInterval(dotclear.dmLastSpamsCount, 30 * 1000);
      }
    }
  }
});
