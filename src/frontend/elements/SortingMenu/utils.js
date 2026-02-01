import React from 'react';
import { createElement, Component } from '@plesk/plesk-ext-sdk';


export const handleSortByChange = function (sortKey) {
    this.setState({ sortingBy: sortKey, sortingDirection: 'ASC' });
};

export const handleSortDirectionChange = function (direction) {
    this.setState({ sortingDirection: direction });
};