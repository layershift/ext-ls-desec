import React from 'react';
import { createElement, Component } from '@plesk/plesk-ext-sdk';
import { Toolbar, ToolbarGroup, Button, SearchBar, Dropdown } from '@plesk/ui-library';
import { SortMenu } from "../SortingMenu/SortingMenu"


const DomainListToolbar = ({
   selectedDomains,
   sortingBy,
   sortingDirection,
   addButtonState,
   syncButtonState,
   searchQuery,
   allSelectedAreRegistered,
   allSelectedAreNotRegistered,
   handleAddDomainToDesec,
   handleDNSRecordsSync,
   enableBulkAutoSync,
   disableBulkAutoSync,
   handleSearchChange,
   handleSortByChange,
   handleSortDirectionChange

}) => {
    const hasSelection = selectedDomains.size > 0;

    return (
        <Toolbar>
            <ToolbarGroup title="deSEC Actions">
                <Button
                    onClick={handleAddDomainToDesec}
                    disabled={!selectedDomains.size > 0 || !allSelectedAreNotRegistered}
                    state={addButtonState}
                >
                    Add to deSEC
                </Button>
                <Button
                    onClick={handleDNSRecordsSync}
                    disabled={!selectedDomains.size > 0 || !allSelectedAreRegistered}
                    state={syncButtonState}
                >
                    Sync DNS
                </Button>
            </ToolbarGroup>

            <ToolbarGroup title="Bulk Actions">
                <Button
                    onClick={() => enableBulkAutoSync("true")}
                    disabled={!selectedDomains.size > 0 || !allSelectedAreRegistered}
                >
                    Enable Auto-Sync
                </Button>
                <Button
                    onClick={() => disableBulkAutoSync("false")}
                    disabled={!selectedDomains.size > 0 || !allSelectedAreRegistered}
                >
                    Disable Auto-Sync
                </Button>
            </ToolbarGroup>

            <div
                style={{
                    flex: 1,
                    display: "flex",
                    justifyContent: "flex-end",
                }}
            >
                <ToolbarGroup>
                    <Dropdown
                        menu={
                            <SortMenu
                                sortingBy={sortingBy}
                                sortingDirection={sortingDirection}
                                handleSortByChange={handleSortByChange}
                                handleSortDirectionChange={handleSortDirectionChange}
                            />
                        }
                    >
                        <Button caret>
                            {`Sort: ${
                                sortingBy === "domain-name" ? "Domain Name" : "Last Sync Status"
                            } (${sortingDirection === "ASC" ? "A→Z" : "Z→A"})`}
                        </Button>
                    </Dropdown>
                </ToolbarGroup>
                <SearchBar
                    value={searchQuery}
                    onChange={handleSearchChange}
                    placeholder="Search domains..."
                />
            </div>
        </Toolbar>
    );
};

export default DomainListToolbar;
