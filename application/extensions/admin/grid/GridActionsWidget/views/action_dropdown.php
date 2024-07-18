<?php

/**
 * @var array $dropdownItems
 */

?>
<div id="<?= 'dropdown_' . $id; ?>" class="dropdown ls-action_dropdown">
    <button aria-label="More Option" class="btn btn-sm btn-outline-secondary ls-dropdown-toggle" data-bs-toggle="dropdown" type="button" aria-expanded="false">
        <i class="ri-more-fill"></i>
    </button>
    <ul id="<?= 'dropdownmenu_' . $id; ?>" class="dropdown-menu custom-actions">
        <?php foreach ($dropdownItems as $dropdownItem) : ?>
            <?php $enabledCondition = $dropdownItem['enabledCondition'] ?? true ?>
            <li>
                <div data-bs-toggle="tooltip" title="<?= $dropdownItem['tooltip'] ?? '' ?>">
                    <?php
                    if ($enabledCondition) {
                    ?>
                        <a id="<?= $dropdownItem['linkId'] ?? '' ?>" class="dropdown-item <?= $enabledCondition ? "" : "disabled" ?> <?= $dropdownItem['linkClass'] ?? '' ?>" href="<?= $dropdownItem['url'] ?? '#' ?>" role="button" <?php if (isset($dropdownItem['linkAttributes']) && is_array($dropdownItem['linkAttributes'])) : ?> <?php foreach ($dropdownItem['linkAttributes'] as $attribute => $value) : ?> <?= "$attribute='$value'" ?> <?php endforeach; ?> <?php endif; ?>>
                            <?php if (isset($dropdownItem['iconClass'])) : ?>
                                <i class="<?= $dropdownItem["iconClass"] ?>"></i>
                            <?php endif; ?>
                            <?= $dropdownItem['title'] ?>
                        </a>
                    <?php
                    } else {
                    ?>
                        <div id="<?= $dropdownItem['linkId'] ?? '' ?>" class="dropdown-item <?= $enabledCondition ? "" : "disabled" ?> <?= $dropdownItem['linkClass'] ?? '' ?>" href="<?= $dropdownItem['url'] ?? '#' ?>" role="button" <?php if (isset($dropdownItem['linkAttributes']) && is_array($dropdownItem['linkAttributes'])) : ?> <?php foreach ($dropdownItem['linkAttributes'] as $attribute => $value) : ?> <?= "$attribute='$value'" ?> <?php endforeach; ?> <?php endif; ?>>
                            <?php if (isset($dropdownItem['iconClass'])) : ?>
                                <i class="<?= $dropdownItem["iconClass"] ?>"></i>
                            <?php endif; ?>
                            <?= $dropdownItem['title'] ?>
                        </div>
                    <?php
                    }
                    ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>