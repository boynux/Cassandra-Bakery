<?php
class Bakery
{
	const NumberColumnFamily = 'number';
	const ChoosingColumnFamily = 'choosing';

	public function __construct ( $id = null, $hostname = '127.0.0.1', $keyspace = 'bakery' ) 
	{
		if ( $id == null ) 
			$this->id = getmypid ();
		else
			$this->id = intval ($id);

		$this->initialize ( $hostname, $keyspace );
	}

	private function initialize ( $hostname, $keyspace )
	{
		$this->pool = new phpcassa\Connection\ConnectionPool ( $keyspace, array ($hostname) );
		$this->number = new phpcassa\ColumnFamily ( $this->pool, self::NumberColumnFamily );
		$this->choosing = new phpcassa\ColumnFamily ( $this->pool, self::ChoosingColumnFamily );
	}

	public function __destruct ()
	{
		$this->release ();
		$this->pool->close ();		
	}

	public function acquire ()
	{
		// setup choosing ...
		$this->choosing->insert ( 1, array ( $this->id => true ),  null, 300 );

		// choose your number ...
		try {
			$my_number = max ( $this->number->get ( 1 ) ) + 1;
		} catch ( cassandra\NotFoundException $e ) {
			$my_number = 1;
		}

		// update CF
		$this->number->insert ( 1, array ( $this->id => $my_number ), null, 3600 );
		$this->choosing->insert ( 1, array ( $this->id => false ) );

		// Wait for everyone with higher priority.
		foreach ( $this->choosing->get ( 1 ) as $index => $status ) {
			try {
				while ( current ( $this->choosing->get (1, null, array ( $index ) ) ) ) { usleep (100); }
			} catch ( cassandra\NotFoundException $e ) {
				;
			}
			do {
				try {
					usleep (100);
					$numbers = $this->number->get ( 1, null, array ( $index ) );
				} catch ( cassandra\NotFoundException $e ) {
					break;
				}
			} while ( ( $numbers[$index] < $my_number ) || ( $numbers[$index] == $my_number && $index < $this->id ) );
		}
	}

	public function release () 
	{
		$this->number->remove ( 1, array ( $this->id ) );
		$this->choosing->remove ( 1, array ( $this->id ) );
	}
}
?>
