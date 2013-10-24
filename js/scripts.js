(function($) {
    "use strict";
    $(function() {
        // Store object for later use
        var $widget = $('#todo_list');

        // Switch todo reordering when one of them is updated
        var reorderOnUpdate = false;

        // Go into editing mode
        $widget.on('click', '.todo-edit', function() {
            var $input = $(this).parents('li').addClass('editing')
                    .find('.todo-content').prop('disabled', false).focus();
            $input.attr('data-old-content', $input.val());
        });

        // Cancel save
        $widget.on('click', '.todo-cancel', function() {
            var $input = $(this).parents('li').find('.todo-content');
            $input.val($input.attr('data-old-content'));
        });

        // Go into deletion mode
        $widget.on('click', '.todo-delete', function() {
            $(this).parents('li').addClass('deleting');
        });

        // Go into default state
        $widget.on('click', '.todo-button:not(.todo-edit, .todo-delete)', function() {
            $(this).parents('li').removeClass('editing').removeClass('deleting')
                    .find('.todo-content').prop('disabled', true);
        });

        // Update todo done status
        $widget.on('change', '.todo-checkbox', function() {
            var $todo = $(this).parents('li');
            var className;
            if ($(this).is(':checked')) {
                if (reorderOnUpdate) {
                    $todo.appendTo($widget);
                }
                className = 'todo-done';
            } else {
                if (reorderOnUpdate) {
                    $todo.prependTo($widget);
                }
                className = 'todo-restored';
                setTimeout(function() {
                    $todo.removeClass('todo-restored').removeClass('todo-done');
                }, 500);
            }
            $todo.addClass(className);

            apiRequest('update', {
                id: $todo.attr('data-todo-id'),
                done: $(this).is(':checked') ? 1 : 0
            });
        });

        // Delete todo
        $widget.on('click', '.todo-delete-confirm', function() {
            var $todo = $(this).parents('li');
            removeTodoVisually($todo);
            apiRequest('delete', {
                todos: [$todo.attr('data-todo-id')]
            });
        });

        // Save todo content
        $widget.on('click', '.todo-save', function() {
            var $todo = $(this).parents('li');
            var $content = $todo.find('.todo-content');
            if (!$.trim($content.val())) {
                $content.val($content.attr('data-old-content'));
                return false;
            }
            $todo.addClass('todo-added');
            apiRequest('update', {
                id: $todo.attr('data-todo-id'),
                content: $content.val()
            }, function() {
                if (!$todo.find('.todo-checkbox').is(':checked')) {
                    setTimeout(function() {
                        $todo.removeClass('todo-added');
                    }, 500);
                }
            });
        });

        // Remove completed todos
        $('#remove_completed').click(function() {
            var completed = [];
            jQuery.each($(".todo-checkbox:checked"), function(index, element) {
                var $todo = $(this).parents('li');
                var todoId = parseInt($todo.attr('data-todo-id'));
                if (todoId) {
                    completed.push($todo.attr('data-todo-id'));
                }
                removeTodoVisually($todo);
            });
            apiRequest('delete', {todos: completed});
        });

        // Remove todo from the list, but don't touch the api
        function removeTodoVisually($todo) {
            $todo.addClass('todo-deleted').slideUp('fast');
            setTimeout(function() {
                $todo.remove();
            }, 200);
        }

        // Form submit
        $('#new_todo_form').submit(function(e) {
            e.preventDefault();
            var params = $(this).serialize();
            var formData = $(this).serializeArray();
            var $content = $('#new_todo_content');
            var trimmed = $.trim($content.val());
            if (trimmed) {
                addTodo({
                    content: trimmed
                });
                $(this).trigger('reset');
                $content.focus();
            }
        });

        // Save on enter
        $widget.on('keyup', '.todo-content', function(event) {
            if (event.which == 13) {
                $(this).parents('li').find('.todo-save').click();
            } else if (event.which == 27) {
                $(this).parents('li').find('.todo-cancel').click();
            }
        });

        // Create a new todo
        function addTodo(data, fillOnly, append) {
            var append = append === true ? true : false;
            var fillOnly = fillOnly === true ? true : false;
            var $tpl = $('#todo_template li').clone().attr('data-todo-id', data.id);

            $tpl.find('.todo-content').val(data.content);
            $tpl.find('.todo-checkbox').prop('checked', parseInt(data.done) ? true : false);
            $tpl.hide().addClass('todo-added');
            append ? $tpl.appendTo($widget) : $tpl.prependTo($widget);
            $tpl.slideDown(100);

            if (parseInt(data.done)) {
                $tpl.addClass('todo-done');
            }

            if (!fillOnly) {
                apiRequest('create', data, function(response) {
                    $tpl.attr('data-todo-id', response.data.insert_id);
                });
            }
            setTimeout(function() {
                $tpl.removeClass('todo-added');
            }, 500);
        }

        // Dragend event, update database sorting
        function sortTodos() {
            var order = [];
            $('#todo_list li').each(function(index, element) {
                order.push($(element).attr('data-todo-id'));
            });
            apiRequest('reorder', {
                'order': order
            });
        }

        function apiRequest(action, data, success, failure) {
            var $status = $('#status').show().text('Updating...').animate({
                opacity: 1
            }, 200);
            if (['create', 'read', 'update', 'delete', 'reorder'
            ].indexOf(action) === -1) {
                throw 'Invalid request, ' + action + ' not supported';
            }
            var method = action === 'read' ? 'get' : 'post';

            $.ajax({
                url: TodoAjax.url,
                type: method,
                data: $.extend(data || {}, {
                    action: 'todo_' + action,
                    _wpnonce: TodoAjax._wpnonce
                }),
                dataType: 'json',
                success: function(response, status, xhr) {
                    // Excecute callback if there is one
                    if (typeof success === 'function') {
                        success(response);
                    }
                    // Print out success status
                    $status.text('Success').animate({
                        opacity: 0
                    }, 200);

                    // Update nonce value
                    if (response._wpnonce) {
                        TodoAjax._wpnonce = response._wpnonce;
                    }
                }, error: function(xhr, status, error) {
                    // Excecute callback if there is one
                    if (typeof failure === 'function') {
                        failure(xhr, status, error);
                    }

                    // Update status
                    $status.text('Request Failed').animate({
                        opacity: 0
                    }, 200);
                }
            });
        }

        // Plugins
        $('#todo_list').sortable({
            handle: '.todo-handle'
        }).bind('sortupdate', sortTodos);

        // Fetch todos
        apiRequest('read', {}, function(response) {
            jQuery.each(response.data, function(index, element) {
                addTodo(element, true, true);
            });
        });

    });
}(jQuery));