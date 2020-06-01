<li id="client_<?php echo $client->getID(); ?>_<?php echo $user->getID(); ?>_item">
    <?php include_component('main/userdropdown', array('user' => $user)); ?>
    <button class="button-silver button-icon" onclick="Pachno.UI.Dialog.show('<?php echo __('Do you really want to remove the user from this client?'); ?>', '<?php echo __('Removing the user from this client will remove all automatically inherited permissions from the user. You can easily add the user back to the client in the future.'); ?>', {yes: {click: function() {Pachno.Config.Client.removeMember('<?php echo make_url('configure_users_remove_client_member', array('user_id' => $user->getID(), 'client_id' => $client->getID())); ?>', <?php echo $client->getID(); ?>, <?php echo $user->getID(); ?>); }}, no: { click: Pachno.UI.Dialog.dismiss }});return false;"><?= fa_image_tag('times'); ?></button>
</li>
