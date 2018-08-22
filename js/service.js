/*global $, dotclear */
'use strict';

dotclear.dmLastSpamsCleanText = function(str) {
  return str.replace(/[&<>]/g, function(t) {
    var tagsToReplace = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;'
    };
    return tagsToReplace[t] || t;
  });
};

dotclear.dmLastSpamsCount = function() {
  var params = {
    f: 'dmLastSpamsCount',
    xd_check: dotclear.nonce,
  };
  $.get('services.php', params, function(data) {
    if ($('rsp[status=failed]', data).length > 0) {
      // For debugging purpose only:
      // console.log($('rsp',data).attr('message'));
      window.console.log('Dotclear REST server error');
    } else {
      var nb_spams = Number($('rsp>check', data).attr('ret'));
      if (nb_spams != dotclear.dmLastSpams_SpamCount) {
        // First pass or spam counter changed
        var icon_com = $('#dashboard-main #icons p a[href="comments.php"]');
        if (icon_com.length) {
          // Icon exists on dashboard
          icon_com = icon_com.parent();
          // Remove badge if exists
          var spam_badge = icon_com.children('span.badge');
          if (spam_badge.length) {
            spam_badge.remove();
          }
          // Add new badge if some spams exist
          if (nb_spams > 0) {
            // Badge on icon
            var xml = '<span class="badge badge-block badge-block-icon">' + nb_spams + '</span>';
            icon_com.append(xml);
          }
        }
        dotclear.dmLastSpams_SpamCount = nb_spams;
      }
    }
  });
};
dotclear.dmLastSpamsCheck = function() {
  var params = {
    f: 'dmLastSpamsCheck',
    xd_check: dotclear.nonce,
    last_id: dotclear.dmLastSpams_LastSpamId
  };
  $.get('services.php', params, function(data) {
    if ($('rsp[status=failed]', data).length > 0) {
      // For debugging purpose only:
      // console.log($('rsp',data).attr('message'));
      window.console.log('Dotclear REST server error');
    } else {
      var new_spams = Number($('rsp>check', data).attr('ret'));
      if (new_spams > 0) {
        var args = {
          f: 'dmLastSpamsRows',
          xd_check: dotclear.nonce,
          stored_id: dotclear.dmLastSpams_LastSpamId,
          last_id: $('rsp>check', data).attr('last_id'),
          last_counter: dotclear.dmLastSpams_LastCounter
        };
        // Store last comment id
        dotclear.dmLastSpams_LastSpamId = $('rsp>check', data).attr('last_id');
        // Get new list
        $.get('services.php', args, function(data) {
          if ($('rsp[status=failed]', data).length > 0) {
            // For debugging purpose only:
            // console.log($('rsp',data).attr('message'));
            window.console.log('Dotclear REST server error');
          } else {
            if (Number($('rsp>rows', data).attr('ret')) > 0) {
              // Display new comments
              var xml = $('rsp>rows', data).attr('list');
              // Replace current list with the new one
              if ($('#last-spams span.badge').length) {
                $('#last-spams span.badge').remove();
              }
              if ($('#last-spams ul').length) {
                $('#last-spams ul').remove();
              }
              if ($('#last-spams p').length) {
                $('#last-spams p').remove();
              }
              var counter = Number($('rsp>rows', data).attr('counter'));
              if (counter > 0) {
                dotclear.dmLastSpams_LastCounter = Number(dotclear.dmLastSpams_LastCounter) + counter;
                if (dotclear.dmLastSpams_Badge) {
                  // Badge on module
                  xml = '<span class="badge badge-block">' + dotclear.dmLastSpams_LastCounter + '</span>' + xml;
                  // Badge on menu item
                  if ($('#main-menu li span.badge').length) {
                    $('#main-menu li span.badge').remove();
                  }
                  var badge = '<span class="badge badge-inline">' + dotclear.dmLastSpams_LastCounter + '</span>';
                  $('#main-menu li a[href="comments.php"]').after(badge);
                }
              }
              $('#last-spams h3').after(xml);
              // Bind every new lines for viewing comment content
              $.expandContent({
                lines: $('#last-spams li.line'),
                callback: dotclear.dmLastSpamsView
              });
              $('#last-spams ul').addClass('expandable');
            }
          }
        });
      }
    }
  });
};
dotclear.dmLastSpamsView = function(line, action) {
  action = action || 'toggle';
  var spamId = $(line).attr('id').substr(4);
  var li = document.getElementById('dmlse' + spamId);
  if (!li && (action == 'toggle' || action == 'open')) {
    li = document.createElement('li');
    li.id = 'dmlse' + spamId;
    li.className = 'expand';
    // Get comment content
    $.get('services.php', {
      f: 'getCommentById',
      id: spamId
    }, function(data) {
      var rsp = $(data).children('rsp')[0];
      if (rsp.attributes[0].value == 'ok') {
        var comment = $(rsp).find('comment_display_content').text();
        if (comment) {
          comment = dotclear.dmLastSpamsCleanText(comment);
          $(li).append(comment);
        }
      } else {
        window.alert($(rsp).find('message').text());
      }
    });
    $(line).toggleClass('expand');
    line.parentNode.insertBefore(li, line.nextSibling);
  } else if (li && li.style.display == 'none' && (action == 'toggle' || action == 'open')) {
    $(li).css('display', 'block');
    $(line).addClass('expand');
  } else if (li && li.style.display != 'none' && (action == 'toggle' || action == 'close')) {
    $(li).css('display', 'none');
    $(line).removeClass('expand');
  }
};
$(function() {
  $.expandContent({
    lines: $('#last-spams li.line'),
    callback: dotclear.dmLastSpamsView
  });
  $('#last-spams ul').addClass('expandable');
  if (dotclear.dmLastSpams_AutoRefresh) {
    // Auto refresh requested : Set 30 seconds interval between two checks for new comments and spam counter check
    dotclear.dmLastSpams_Timer = setInterval(dotclear.dmLastSpamsCheck, 30 * 1000);
    if (dotclear.dmLastSpams_Badge) {
      $('#last-spams').addClass('badgeable');
      var icon_com = $('#dashboard-main #icons p a[href="comments.php"]');
      if (icon_com.length) {
        // Icon exists on dashboard
        icon_com.parent().addClass('badgeable');
        // First pass
        dotclear.dmLastSpamsCount();
        // Then fired every 30 seconds
        dotclear.dmLastSpams_TimerSpam = setInterval(dotclear.dmLastSpamsCount, 30 * 1000);
      }
    }
  }
});
