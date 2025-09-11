import React from 'react';
import { propTypes } from './utils/constants';
import { createElement, Component } from '@plesk/plesk-ext-sdk';
import { handleSortByChange, handleSortDirectionChange } from "./elements/SortingMenu/utils";
import { getDomainsInfo, getDomainRetentionStatus, saveDomainRetentionStatus, checkTokenExists, validateToken} from './api-calls';
import DomainListToolbar from "elements/Toolbar/Toolbar";
import { states } from './utils/states';
import { handleAddDomainToDesec, handleDNSRecordsSync, handleSearchChange } from './elements/Toolbar/utils';
import { handleSelectAll, handleCheckboxChange, handleSyncChange, handleToastClose, handleBulkAutoSync } from "./utils/methods";

import {
    Link,
    Label,
    Switch,
    Tabs,
    Tab,
    SwitchesPanel,
    SwitchesPanelItem,
    Paragraph,
    Toaster,
    SkeletonText,
    List,
    ListEmptyView,
    Checkbox,
    Text,
    Drawer,
    Section,
    FormFieldText
} from '@plesk/ui-library';

export default class App extends React.PureComponent {
    static propTypes = propTypes;
    state = states;

    async componentDidMount() {
        await checkTokenExists.call(this);

        if (this.state.tokenStatus === "true") {
            await getDomainsInfo.call(this);
        } else {
            this.setState({
                emptyViewTitle: "Missing credentials!",
                emptyViewDescription: "The deSEC token used within the extension is missing or it was misplaced! Please check the pm_Settings object or panel.ini.",
                isFormOpen: true,
                listLoading: false
            });
        }

         await getDomainRetentionStatus.call(this);
    }

    getFilteredSortedDomains() {
        const { domains, searchQuery, sortingBy, sortingDirection } = this.state;

        const hasChanged =
            this._memoCache === undefined ||
            this._memoCache.domains !== domains ||
            this._memoCache.searchQuery !== searchQuery ||
            this._memoCache.sortingBy !== sortingBy ||
            this._memoCache.sortingDirection !== sortingDirection;

        if (!hasChanged) {
            return this._memoCache.result;
        }

        const filtered = domains.filter(d =>
            d['domain-name']?.toLowerCase().includes(searchQuery.trim().toLowerCase())
        );

        const sorted = [...filtered].sort((a, b) => {
            const getValue = (domain, key) => domain[key]?.toLowerCase() ?? '';
            const aVal = getValue(a, sortingBy);
            const bVal = getValue(b, sortingBy);

            if (aVal < bVal) return sortingDirection === 'ASC' ? -1 : 1;
            if (aVal > bVal) return sortingDirection === 'ASC' ? 1 : -1;
            return 0;
        });

        this._memoCache = {
            domains,
            searchQuery,
            sortingBy,
            sortingDirection,
            result: sorted
        };

        return sorted;
    }


    renderColumns(allSelected, isIndeterminate, selectedDomains) {
        return [
            {
                key: 'checkbox',
                title: <Checkbox checked={allSelected} indeterminate={isIndeterminate} onChange={handleSelectAll.bind(this)} />,
                width: '5%',
                render: row => (
                    <Checkbox
                        checked={selectedDomains.has(row["domain-id"])}
                        onChange={() => handleCheckboxChange.call(this, row["domain-id"])}
                        disabled={!row["dns-status"]}
                    />
                )
            },
            {
                key: 'domain-name',
                title: 'Domain',
                render: row => <Link to={`#${row['domain-name']}`}>{row['domain-name']}</Link>
            },
            {
                key: 'last-sync-status',
                title: 'Last Sync Status',
                render: row => {
                    const status = row["last-sync-status"];
                    if (status.startsWith("SUCCESS")) return <Label icon="check-mark-circle-filled" size="sm" view="light" intent="success">{status}</Label>;
                    if (status.startsWith("FAILED")) return <Label icon="exclamation-mark-circle" size="sm" view="light" intent="danger">{status}</Label>;
                    return <Text intent="muted">No data</Text>;
                }
            },
            {
                key: 'last-sync-attempt',
                title: 'Last Sync Attempt',
                render: row => <Text intent={row['last-sync-attempt'] === "No date" ? "muted" : undefined}>{row['last-sync-attempt']}</Text>
            },
            {
                key: 'dns-status',
                title: "Plesk DNS Status",
                render: row => row['dns-status'] ? <Label intent="success">Active</Label> : <Label intent="warning">Disabled</Label>

            },

            {
                key: 'domain-desec-status',
                title: "deSEC Status",
                render: row => {
                    if(row['desec-status'] === "Registered") {
                        return (<Label intent="success">Registered</Label>)
                    } else if(row['desec-status'] === "Error") {
                        return (<Label intent="danger">Error</Label>)
                    } else {
                        return (<Label intent="warning">Not Registered</Label>)
                    }
                }
            },

            {
                key: 'auto-sync-desec',
                title: 'Auto-Sync',
                render: row => {
                    const domainId = row["domain-id"];
                    const enabled = row["auto-sync-status"] === "true" && row["desec-status"] === "Registered";
                    const disabled = !row["dns-status"] || row["desec-status"] === "Not Registered" || row["desec-status"] === "Error";
                    return (
                        <div style={{ display: 'flex', alignItems: 'center' }}>
                            <Switch checked={enabled} onChange={() => handleSyncChange.call(this, domainId)} disabled={disabled} />
                            <span style={{ marginLeft: 10 }}>{enabled ? 'Enabled' : 'Disabled'}</span>
                        </div>
                    );
                }
            }
        ];
    }

    render() {
        const {
            domains,
            selectedDomains,
            retainDomainCheck,
            toasts,
            listLoading,
            sortingBy,
            sortingDirection,
            addButtonState,
            syncButtonState,
            searchQuery,
            tokenStatus,
            formState,
            isFormOpen,
            emptyViewTitle,
            emptyViewDescription,
        } = this.state;

        const filtered = domains.filter(d => d['domain-name']?.toLowerCase().includes(searchQuery.trim().toLowerCase()));
        const allSelected = filtered.length > 0 && selectedDomains.size === filtered.length;
        const isIndeterminate = selectedDomains.size > 0 && selectedDomains.size < filtered.length;
        const selectedDomainObjects = domains.filter(d => selectedDomains.has(d['domain-id']));
        const allSelectedAreRegistered = selectedDomainObjects.every(d => d['desec-status'] === 'Registered');
        const allSelectedAreNotRegistered = selectedDomainObjects.every(d => d['desec-status'] === 'Not Registered' || d['desec-status'] === 'Error');
        const sorted = this.getFilteredSortedDomains();
        const columns = this.renderColumns(allSelected, isIndeterminate, selectedDomains);

        return (
            <div>
                {tokenStatus === "false" && (
                    <Drawer
                        title="deSEC Credentials"
                        size="md"
                        description={""}
                        isOpen={isFormOpen}
                        onClose={() => {
                            this.setState(({
                                isFormOpen: false,
                            }));
                        }}
                        form={{
                            onSubmit: () => {
                                this.setState({ formState: 'submit' });
                                validateToken.bind(this)();
                            },
                            applyButton: false,
                            submitButton: {
                                children: formState === 'submit' ? 'Saving...' : 'Save'
                            },
                            state: formState,
                            hideButton: true
                        }}
                        closingConfirmation={true}
                    >
                        <Paragraph>Login to <Link href="https://desec.io">desec.io</Link> and create a token with <strong>can create domains</strong> and <strong>can delete domains</strong> permissions. Paste the token secret value below:</Paragraph>
                        <br />


                        <Section title="deSEC API Token" >
                            <FormFieldText
                                label="API Token"
                                size="lg"
                                required
                                onChange={(value) => this.setState({ inputToken: value })}
                            />
                        </Section>
                    </Drawer>

                )}

                <Tabs active={1} monospaced>
                    <Tab key={1} title="Control Panel" icon="cd-up-in-cloud">
                        {listLoading ? (
                            <SkeletonText lines={5} />
                        ) : (
                            <>
                                <DomainListToolbar
                                    selectedDomains={selectedDomains}
                                    sortingBy={sortingBy}
                                    sortingDirection={sortingDirection}
                                    addButtonState={addButtonState}
                                    syncButtonState={syncButtonState}
                                    searchQuery={searchQuery}
                                    allSelectedAreRegistered={allSelectedAreRegistered}
                                    allSelectedAreNotRegistered={allSelectedAreNotRegistered}
                                    handleAddDomainToDesec={handleAddDomainToDesec.bind(this)}
                                    handleDNSRecordsSync={handleDNSRecordsSync.bind(this)}
                                    enableBulkAutoSync={handleBulkAutoSync.bind(this, true)}
                                    disableBulkAutoSync={handleBulkAutoSync.bind(this, false)}
                                    handleSearchChange={handleSearchChange.bind(this)}
                                    handleSortByChange={handleSortByChange.bind(this)}
                                    handleSortDirectionChange={handleSortDirectionChange.bind(this)}
                                />
                                <List
                                    columns={columns}
                                    data={sorted}
                                    rowKey={row => row['domain-id']}
                                    sortColumn={{ sortBy: sortingBy, direction: sortingDirection }}
                                    emptyView={<ListEmptyView title={emptyViewTitle} description={emptyViewDescription} />}
                                />
                            </>
                        )}
                    </Tab>
                    <Tab key={2} title="Settings" icon="gear">
                        <SwitchesPanel title="Settings">
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '15px' }}>
                                <SwitchesPanelItem
                                    switchProps={{ checked: retainDomainCheck === "true" }}
                                    onChange={saveDomainRetentionStatus.bind(this)}
                                    title="Domain retention in deSEC"
                                    description="Keep your DNS zone active in deSEC even if the domain is removed from Plesk."
                                    fullDescription={<Paragraph>This option's state will reflect whether or not you prefer that a domain, if deleted from Plesk, to be retained (along with its DNS zone) in deSEC.</Paragraph>}
                                    style={{ width: 400 }}
                                />
                            </div>
                        </SwitchesPanel>
                    </Tab>
                </Tabs>
                <Toaster toasts={toasts} onToastClose={handleToastClose.bind(this)} />
            </div>
        );
    }
}