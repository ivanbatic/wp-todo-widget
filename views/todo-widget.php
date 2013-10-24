<form class="new-todo" id="new_todo_form">
    <div>
        <span class="cell" style="width: 100%">
            <input autocomplete="off" required pattern=".+" id="new_todo_content" class="todo-content" type="text" name="content" style="width:100%" autofocus placeholder="<?=__('Create a new Todo')?>"/>
        </span>
        <span class="cell">
            <input id="add_todo" type="submit" class="button-primary" value="<?=__('Add a Todo')?>"/>
        </span>
    </div>
</form>
<ul id="todo_list"></ul>
<div class="widget-row">
    <span class="cell" style="width:100%" id="status"></span>
    <span class="cell">
        <button class="todo-button" id="remove_completed"><?=__('Remove Completed Todos')?></button>
    </span>
</div>


<div id="todo_template">
    <li data-todo-id="">
        <div class="todo-handle cell">::</div>
        <div class="todo-checkbox-container cell">
            <input type="checkbox" class="todo-checkbox" name="done"/>
        </div>
        <span class="todo-content-container cell">
            <input type="text" class="todo-content" name="content" disabled/>
        </span>
        <div class="todo-actions cell">
            <button class="todo-button todo-element todo-group-delete todo-delete-confirm"><?=__('Confirm Deletion')?></button>
            <span class="divider todo-element todo-group-delete"> | </span>
            <button class="todo-button todo-element todo-group-delete todo-delete-cancel"><?=__('Cancel')?></button>

            <button class="todo-button todo-element todo-group-edit todo-save"><?=__('Save')?></button>
            <span class="divider todo-element todo-group-edit"> | </span>
            <button class="todo-button todo-element todo-group-edit todo-cancel"><?=__('Cancel')?></button> 

            <button class="todo-button todo-element todo-group-default todo-edit"><?=__('Edit')?></button>
            <span class="divider todo-element todo-group-default"> | </span>
            <button class="todo-button todo-element todo-group-default todo-delete"><?=__('Delete')?></button>
        </div>
    </li>
</div>