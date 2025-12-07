import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { DatabaseConnection } from '@/types/database-connection';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { Database, Download, HardDrive, RefreshCw, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

interface Backup {
    id: number;
    filename: string;
    size: string | null;
    path: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    progress: number;
    log: string | null;
    created_at: string;
    download_url: string | null;
    delete_url: string;
    restore_url: string | null;
}

interface Props {
    connection: DatabaseConnection;
    connections: DatabaseConnection[];
    backupDisks: BackupDisk[];
}

interface BackupDisk {
    id: number;
    name: string;
    driver: string;
    is_default: boolean;
}

export default function Show({ connection, connections: allConnections, backupDisks }: Props) {
    const [backups, setBackups] = useState<Backup[]>([]);
    const [selectedTargetConnectionId, setSelectedTargetConnectionId] = useState<string>('');
    const [selectedBackupDiskId, setSelectedBackupDiskId] = useState<string>('');
    const [selectedBackupType, setSelectedBackupType] = useState<string>('full');
    const [loading, setLoading] = useState(false);
    const prevBackupsRef = useRef<Backup[]>([]);
    const [deleteDialog, setDeleteDialog] = useState<{ open: boolean; backup: Backup | null }>({ open: false, backup: null });
    const [restoreDialog, setRestoreDialog] = useState<{ open: boolean; backup: Backup | null; loading: boolean }>({ open: false, backup: null, loading: false });

    const backupTypes = [
        { value: 'full', label: 'Full (Structure + Data)' },
        { value: 'structure', label: 'Structure Only' },
        { value: 'data', label: 'Data Only' },
        { value: 'public_schema', label: 'Public Schema Only' },
    ];

    const fetchBackups = async () => {
        try {
            const response = await axios.get(`/connections/${connection.id}/backups`);
            setBackups(response.data);
        } catch (error) {
            console.error('Failed to fetch backups', error);
        }
    };

    useEffect(() => {
        // Set default disk
        const defaultDisk = backupDisks.find(d => d.is_default);
        if (defaultDisk) {
            setSelectedBackupDiskId(defaultDisk.id.toString());
        }

        fetchBackups();

        // Poll every 1 second for real-time updates
        const interval = setInterval(() => {
            setBackups((currentBackups) => {
                const hasPending = currentBackups.some(b => b.status === 'pending' || b.status === 'processing');
                if (hasPending) {
                    fetchBackups();
                }
                return currentBackups;
            });
        }, 1000);

        return () => clearInterval(interval);
    }, [connection.id]);

    useEffect(() => {
        backups.forEach(backup => {
            const prevBackup = prevBackupsRef.current.find(b => b.id === backup.id);
            if (prevBackup && prevBackup.status === 'processing' && backup.status === 'failed') {
                toast.error(`Backup failed: ${backup.log || 'Unknown error'}`);
            }
            if (prevBackup && prevBackup.status === 'processing' && backup.status === 'completed') {
                toast.success('Backup completed successfully');
            }
        });
        prevBackupsRef.current = backups;
    }, [backups]);

    const handleBackup = () => {
        setLoading(true);
        router.post(`/connections/${connection.id}/backups`, {
            backup_disk_id: selectedBackupDiskId || undefined,
            backup_type: selectedBackupType,
        }, {
            onSuccess: () => {
                toast.success('Backup started in background');
                fetchBackups();
                setLoading(false);
            },
            onError: () => {
                toast.error('Failed to start backup');
                setLoading(false);
            }
        });
    };

    const confirmRestore = (backup: Backup) => {
        setSelectedTargetConnectionId(connection.id.toString());
        setRestoreDialog({ open: true, backup, loading: false });
    };

    const handleRestore = () => {
        if (!restoreDialog.backup || !selectedTargetConnectionId) return;

        const targetConnection = allConnections.find(c => c.id.toString() === selectedTargetConnectionId);
        if (!targetConnection) {
            toast.error('Please select a target connection');
            return;
        }

        setRestoreDialog(prev => ({ ...prev, loading: true }));

        // Show persistent notification
        const restoreToastId = toast.loading(`Starting restore to ${targetConnection.name}...`, {
            duration: Infinity,
        });

        // Use axios.post with CSRF token configured in app.tsx
        axios.post(restoreDialog.backup.restore_url!, {
            target_connection_id: selectedTargetConnectionId,
        })
        .then((response) => {
            // Store the restore ID from the response
            const restoreId = response.data?.restore_id;

            if (!restoreId) {
                toast.error('Failed to get restore ID', {
                    id: restoreToastId,
                });
                setRestoreDialog({ open: false, backup: null, loading: false });
                return;
            }

            // Keep the notification open and update it
            toast.loading(`Restore in progress to ${targetConnection.name}...`, {
                id: restoreToastId,
                duration: Infinity,
            });
            setRestoreDialog({ open: false, backup: null, loading: false });

            // Poll for restore completion
            const pollInterval = setInterval(() => {
                // Poll the restore status
                axios.get(`/restores/${restoreId}/status`)
                    .then(response => {
                        const { status, progress, log } = response.data;

                        // Update the notification with progress
                        if (status === 'processing' || status === 'pending') {
                            toast.loading(`Restore in progress to ${targetConnection.name}... (${progress}%)`, {
                                id: restoreToastId,
                                duration: Infinity,
                            });
                        }

                        // Check if restore is completed
                        if (status === 'completed') {
                            clearInterval(pollInterval);
                            toast.success(`Restore completed successfully to ${targetConnection.name}`, {
                                id: restoreToastId,
                            });
                        } else if (status === 'failed') {
                            clearInterval(pollInterval);
                            toast.error(`Restore failed on ${targetConnection.name}${log ? ': ' + log : ''}`, {
                                id: restoreToastId,
                            });
                        }
                    })
                    .catch(() => {
                        // Silently fail on polling errors
                    });
            }, 2000);

            // Stop polling after 10 minutes
            setTimeout(() => {
                clearInterval(pollInterval);
            }, 600000);
        })
        .catch((error) => {
            toast.error(error.response?.data?.message || 'Failed to start restore', {
                id: restoreToastId,
            });
            setRestoreDialog({ open: false, backup: null, loading: false });
        });
    };

    const confirmDelete = (backup: Backup) => {
        setDeleteDialog({ open: true, backup });
    };

    const handleDelete = () => {
        if (!deleteDialog.backup) return;

        router.delete(deleteDialog.backup.delete_url, {
            onSuccess: () => {
                toast.success('Backup deleted');
                fetchBackups();
                setDeleteDialog({ open: false, backup: null });
            },
            onError: () => {
                toast.error('Failed to delete backup');
                setDeleteDialog({ open: false, backup: null });
            }
        });
    };

    const getStatusVariant = (status: string) => {
        switch (status) {
            case 'completed': return 'default';
            case 'failed': return 'destructive';
            case 'processing': return 'secondary';
            default: return 'outline';
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Connections', href: '/connections' },
                { title: connection.name, href: `/connections/${connection.id}` },
            ]}
        >
            <Head title={connection.name} />

            <div className="p-6 max-w-6xl mx-auto space-y-6">
                {/* Connection Info Card */}
                <Card>
                    <CardHeader>
                        <div className="flex items-start justify-between">
                            <div className="space-y-3 flex-1">
                                <div className="flex items-center gap-2">
                                    <Database className="h-5 w-5 text-muted-foreground" />
                                    <CardTitle className="text-2xl">{connection.name}</CardTitle>
                                </div>
                                <CardDescription>
                                    {connection.username}@{connection.host}:{connection.port} / {connection.database}
                                </CardDescription>
                                {backupDisks.length > 1 && (
                                    <div className="flex items-center gap-2">
                                        <HardDrive className="h-4 w-4 text-muted-foreground" />
                                        <Select value={selectedBackupDiskId} onValueChange={setSelectedBackupDiskId}>
                                            <SelectTrigger className="w-[250px]">
                                                <SelectValue placeholder="Select backup disk" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {backupDisks.map((disk) => (
                                                    <SelectItem key={disk.id} value={disk.id.toString()}>
                                                        <div className="flex items-center gap-2">
                                                            <span>{disk.name}</span>
                                                            <span className="text-sm text-muted-foreground">
                                                                ({disk.driver}{disk.is_default ? ' - default' : ''})
                                                            </span>
                                                        </div>
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}
                                <div className="flex items-center gap-2">
                                    <Database className="h-4 w-4 text-muted-foreground" />
                                    <Select value={selectedBackupType} onValueChange={setSelectedBackupType}>
                                        <SelectTrigger className="w-[250px]">
                                            <SelectValue placeholder="Select backup type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {backupTypes.map((type) => (
                                                <SelectItem key={type.value} value={type.value}>
                                                    {type.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <Button onClick={handleBackup} disabled={loading} size="lg">
                                {loading ? 'Starting...' : 'Create Backup'}
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div className="space-y-1">
                                <p className="text-sm text-muted-foreground">Driver</p>
                                <p className="font-medium">{connection.driver.toUpperCase()}</p>
                            </div>
                            <div className="space-y-1">
                                <p className="text-sm text-muted-foreground">Host</p>
                                <p className="font-medium">{connection.host}</p>
                            </div>
                            <div className="space-y-1">
                                <p className="text-sm text-muted-foreground">Port</p>
                                <p className="font-medium">{connection.port}</p>
                            </div>
                            <div className="space-y-1">
                                <p className="text-sm text-muted-foreground">Total Backups</p>
                                <p className="font-medium">{backups.length}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Backups Table Card */}
                <Card>
                    <CardHeader>
                        <CardTitle>Backup History</CardTitle>
                        <CardDescription>
                            Manage and restore your database backups
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Filename</TableHead>
                                    <TableHead>Size</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {backups.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={5} className="text-center text-muted-foreground h-32">
                                            No backups found. Create your first backup to get started.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    backups.map((backup) => (
                                        <TableRow key={backup.id}>
                                            <TableCell className="font-mono text-sm">
                                                {backup.filename || 'Pending...'}
                                            </TableCell>
                                            <TableCell>{backup.size || '-'}</TableCell>
                                            <TableCell>
                                                {backup.status === 'processing' ? (
                                                    <div className="space-y-2 min-w-[150px]">
                                                        <div className="flex items-center justify-between">
                                                            <Badge variant="secondary">Processing</Badge>
                                                            <span className="text-sm font-medium">{backup.progress}%</span>
                                                        </div>
                                                        <Progress value={backup.progress} className="h-2" />
                                                    </div>
                                                ) : (
                                                    <div className="space-y-1">
                                                        <Badge variant={getStatusVariant(backup.status)}>
                                                            {backup.status}
                                                        </Badge>
                                                        {backup.status === 'failed' && backup.log && (
                                                            <p className="text-xs text-destructive max-w-[200px] truncate" title={backup.log}>
                                                                {backup.log}
                                                            </p>
                                                        )}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {backup.created_at}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    {backup.status === 'completed' && (
                                                        <>
                                                            <Button variant="outline" size="sm" asChild>
                                                                <a href={backup.download_url!} target="_blank" rel="noreferrer">
                                                                    <Download className="h-4 w-4 mr-1" />
                                                                    Download
                                                                </a>
                                                            </Button>
                                                            <Button
                                                                variant="secondary"
                                                                size="sm"
                                                                onClick={() => confirmRestore(backup)}
                                                            >
                                                                <RefreshCw className="h-4 w-4 mr-1" />
                                                                Restore
                                                            </Button>
                                                        </>
                                                    )}
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() => confirmDelete(backup)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            {/* Delete Confirmation Dialog */}
            <AlertDialog open={deleteDialog.open} onOpenChange={(open) => setDeleteDialog({ open, backup: null })}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Backup</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete this backup? This action cannot be undone.
                            <br />
                            <span className="font-mono text-sm mt-2 block">
                                {deleteDialog.backup?.filename}
                            </span>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDelete} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Restore Confirmation Dialog */}
            <AlertDialog open={restoreDialog.open} onOpenChange={(open) => setRestoreDialog({ open: false, backup: null, loading: false })}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Restore Database</AlertDialogTitle>
                        <AlertDialogDescription>
                            Select the target database connection to restore this backup to.
                            <br />
                            <span className="font-mono text-sm mt-2 block font-semibold text-foreground">
                                {restoreDialog.backup?.filename}
                            </span>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="py-4">
                        <label className="text-sm font-medium mb-2 block">
                            Target Connection
                        </label>
                        <Select value={selectedTargetConnectionId} onValueChange={setSelectedTargetConnectionId}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select a connection" />
                            </SelectTrigger>
                            <SelectContent>
                                {allConnections.map((conn) => (
                                    <SelectItem key={conn.id} value={conn.id.toString()}>
                                        <div className="flex items-center gap-2">
                                            <Database className="h-4 w-4" />
                                            <span>{conn.name}</span>
                                            <span className="text-sm text-muted-foreground">
                                                ({conn.username}@{conn.host}:{conn.port}/{conn.database})
                                            </span>
                                        </div>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-sm text-muted-foreground mt-2">
                            This will overwrite the database at the selected connection.
                        </p>
                    </div>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={restoreDialog.loading}>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleRestore}
                            disabled={!selectedTargetConnectionId || restoreDialog.loading}
                        >
                            {restoreDialog.loading ? 'Starting...' : 'Restore'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
