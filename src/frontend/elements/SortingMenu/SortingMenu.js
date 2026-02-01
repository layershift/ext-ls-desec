import React from 'react';
import { createElement, Component } from '@plesk/plesk-ext-sdk';
import { Menu, MenuItem, MenuDivider, Button } from '@plesk/ui-library';

export const SortMenu = ({
    sortingBy,
    sortingDirection,
    handleSortByChange,
    handleSortDirectionChange

}) => {
    return (


            <Menu>
                <MenuItem
                    onClick={() => handleSortByChange('domain-name')}
                    icon={sortingBy === 'domain-name' ? 'check-mark' : ''}
                >
                    Domain Name
                </MenuItem>
                <MenuItem
                    onClick={() => handleSortByChange('last-sync-status')}
                    icon={sortingBy === 'last-sync-status' ? 'check-mark' : ''}
                >
                    Last Sync Status
                </MenuItem>
                <MenuDivider />
                <MenuItem
                    onClick={() => handleSortDirectionChange('ASC')}
                    icon={sortingDirection === 'ASC' ? 'check-mark' : ''}
                >
                    Ascending
                </MenuItem>
                <MenuItem
                    onClick={() => handleSortDirectionChange('DESC')}
                    icon={sortingDirection === 'DESC' ? 'check-mark' : ''}
                >
                    Descending
                </MenuItem>
            </Menu>

    )
}