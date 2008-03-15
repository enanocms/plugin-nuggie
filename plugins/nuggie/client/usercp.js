/*
 * Nuggie
 * Version 0.1
 * Copyright (C) 2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

function ajaxNuggieDeletePost(id, row)
{
  if ( !confirm('Are you sure you want to permanently delete this blog post?') )
    return false;
  
  _global_ng_row = row;
  
  try
  {
    ajaxPost(makeUrlNS('Special', 'Preferences/Blog/Posts/AjaxHandler'), 'act=delete&post_id=' + id, function()
      {
        if ( ajax.readyState == 4 )
        {
          if ( ajax.responseText == '1' )
          {
            var row = _global_ng_row;
            for ( var i = 0; i < row.childNodes.length; i++ )
            {
              if ( row.childNodes[i].tagName == 'TD' )
              {
                row.childNodes[i].style.backgroundColor = 'transparent';
              }
            }
            var fader = new Spry.Effect.Highlight(row, {to:'#AA0000', duration: 750});
            fader.start();
            setTimeout('_global_ng_row.parentNode.removeChild(_global_ng_row); nuggie_check_postlist_empty();', 750);
          }
          else
          {
            alert(ajax.responseText);
          }
        }
      });
    return false;
  }
  catch(e)
  {
    return true;
  }
}

function nuggie_check_postlist_empty()
{
  if ( document.getElementById('nuggie_postlist').childNodes.length == 1 )
  {
    var td = document.createElement('td');
    td.className = 'row3';
    td.setAttribute('colspan', '6');
    td.appendChild(document.createTextNode('No posts.'));
    td.style.textAlign = 'center';
    var tr = document.createElement('tr');
    tr.appendChild(td);
    document.getElementById('nuggie_postlist').appendChild(tr);
  }
}

function ajaxNuggieTogglePublished(id, obj)
{
  var published = obj.getAttribute('nuggie:published') == '1' ? true : false;
  var newstate = ( published ) ? '0' : '1';
  obj.innerHTML = '<img alt="Loading..." src="' + ajax_load_icon + '" />';
  ajaxPost(makeUrlNS('Special', 'Preferences/Blog/Posts/AjaxHandler'), 'act=publish&post_id=' + id + '&state=' + newstate, function()
    {
      if ( ajax.readyState == 4 )
      {
        if ( ajax.responseText == 'good;1' )
        {
          obj.className = 'row3_green nuggie_publishbtn';
          obj.innerHTML = '<b>Yes</b>';
          obj.setAttribute('nuggie:published', '1');
        }
        else if ( ajax.responseText == 'good;0' )
        {
          obj.className = 'row3_red nuggie_publishbtn';
          obj.innerHTML = 'No';
          obj.setAttribute('nuggie:published', '0');
        }
        else
        {
          alert(ajax.responseText);
        }
      }
    });
}

