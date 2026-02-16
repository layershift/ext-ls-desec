import React from 'react';
import punycode from 'punycode/';
import { propTypes } from './utils/constants';
import { createElement, Component } from '@plesk/plesk-ext-sdk';
import { handleSortByChange, handleSortDirectionChange } from "./elements/SortingMenu/utils";
import { getDomainsInfo, getDomainRetentionStatus, saveDomainRetentionStatus, checkTokenExists, validateToken, getUserEulaDecision, saveUserEulaDecision} from './api-calls';
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
    FormFieldText,
    Dialog
} from '@plesk/ui-library';

export default class App extends Component {

    static propTypes = propTypes;
    state = states;

    loadDomains = async() => {
        const hasToken = this.state.tokenStatus;
        const acceptedEula = this.state.eulaDecision

        if(hasToken && acceptedEula) {
            this.setState({ listLoading: true });
            await getDomainsInfo.call(this);
            this.setState({ listLoading: false });
        }
    }


    componentDidMount = async () => {
        await getUserEulaDecision.call(this);
        await checkTokenExists.call(this);

        if (!this.state.eulaDecision) {
            this.setState({
                listLoading: true,
                privacyPolicyDialog: true,
            });
            return;
        }

        if (!this.state.tokenStatus) {
            this.setState({
                emptyViewTitle: "Missing credentials!",
                emptyViewDescription:
                    "The deSEC token used within the extension is missing or it was misplaced! Please check the pm_Settings object or panel.ini.",
                isFormOpen: true,
                listLoading: false,
            });
            return;
        }

        await this.loadDomains();
        await getDomainRetentionStatus.call(this);

        const observer = window.Jsw.Observer;
        this._onPleskTaskComplete = (payload) => {

            const taskType = payload["type"];
            const additionalData = payload["additionalData"];

            if(taskType === "ext-ls-desec-dns-task_registerdomains") {
                this.setState({ addButtonState: ""})

                if(additionalData) {
                    this.setState(prevState => ({
                        domains: prevState.domains.map(domain => {

                            const taskResult = additionalData[domain["domain-id"]]
                            if(taskResult) {
                                return {
                                    ...domain,
                                    "desec-status": taskResult.status === "Registered" ? "Registered" : "Not Registered",
                                    "auto-sync-status": "true"
                                };
                            }

                            return domain;
                        })
                    }));
                }

            } else if (taskType === "ext-ls-desec-dns-task_syncdnszones") {
                this.setState({ syncButtonState: ""})

                if(additionalData) {
                    this.setState(prevState => ({
                        domains: prevState.domains.map(domain => {
                            const taskResult = additionalData[String(domain["domain-id"])]

                            if (taskResult && taskResult?.code) {
                                return {
                                    ...domain,
                                    "desec-status": "Not Registered",
                                    "last-sync-status": "No data",
                                    "last-sync-attempt": "No date"
                                };
                            } else if(taskResult) {
                                return {
                                    ...domain,
                                    "last-sync-status": taskResult['last_sync_status'],
                                    "last-sync-attempt": taskResult['timestamp']
                                };
                            }

                            return domain;
                        })
                    }));
                }
            }
        };


        observer.addEventListener('plesk:taskComplete', this._onPleskTaskComplete);
    };


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
                render: row => {
                    const asciiDomain = row['domain-name']
                        .split('.')
                        .map(part => punycode.toUnicode(part))
                        .join('.');

                    return (
                        <Link
                            href={row['domain-link']}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {asciiDomain}
                        </Link>
                    );
                }
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
            eulaDecision,
            privacyPolicyDialog
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
                {(eulaDecision !== true) ? (
                    <Dialog
                        isOpen={!!privacyPolicyDialog}
                        title="Privacy Policy & Data Processing"
                        size="sm"
                        onClose={async () => {
                            await saveUserEulaDecision.call(this, false);

                            this.setState({
                                eulaDecision: false,
                                emptyViewTitle: "Privacy policy denied!",
                                emptyViewDescription:
                                    "Acceptance of the Privacy Policy is required to use this extension. To revisit the policy, please refresh the page.",
                                listLoading: false,
                                privacyPolicyDialog: false,
                            });
                        }}
                        form={{
                            onSubmit: async () => {
                                await saveUserEulaDecision.call(this, true);

                                this.setState(
                                    {
                                        eulaDecision: true,
                                        privacyPolicyDialog: false,
                                    },
                                    async () => {
                                        await this.loadDomains();
                                    }
                                );
                            },
                            submitButton: { children: "Agree!" },
                            cancelButton: { children: "I do not agree!" }
                        }}
                    >
                        <Section title="Read the full policy">
                            <Paragraph>
                                <Link href="https://github.com/layershift/ext-ls-desec/blob/main/PRIVACY.md" target="_blank" rel="noopener noreferrer">
                                    Open Privacy Policy
                                </Link>
                            </Paragraph>
                        </Section>

                        <Paragraph>
                            If you do not agree, you will not be able to perform any operations using this extension.
                        </Paragraph>

                    </Dialog>
                ) : null}


                {tokenStatus === null || tokenStatus === undefined || tokenStatus === false && (
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
                        <Paragraph>
                            Login to{" "}
                            <Link href="https://desec.io" target="_blank" rel="noopener noreferrer">
                                desec.io
                            </Link>{" "}
                            and create a token with <strong>can create domains</strong> and{" "}
                            <strong>can delete domains</strong> permissions. Paste the token secret value below:
                        </Paragraph>
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
                                    enableBulkAutoSync={handleBulkAutoSync.bind(this, "true")}
                                    disableBulkAutoSync={handleBulkAutoSync.bind(this, "false")}
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